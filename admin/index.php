<?php

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';

if (!SEC_hasRights('migrator.admin')) {
    COM_accessLog('Unauthorized access attempt to Migrator administration.');
    $display = COM_showMessageText($MESSAGE[29], $MESSAGE[30]);
    echo COM_createHTMLDocument($display, array('pagetitle' => $MESSAGE[30]));
    exit;
}

require_once $_CONF['path'] . 'plugins/migrator/classes/SqlDumpImporter.php';
require_once $_CONF['path'] . 'plugins/migrator/classes/DumpUpload.php';
require_once $_CONF['path'] . 'plugins/migrator/classes/SourceDetector.php';
require_once $_CONF['path'] . 'plugins/migrator/classes/MigrationAnalyzer.php';
require_once $_CONF['path'] . 'plugins/migrator/adapters/LegacyGeeklogAdapter.php';
require_once $_CONF['path'] . 'plugins/migrator/adapters/GlfusionAdapter.php';
require_once $_CONF['path'] . 'plugins/migrator/adapters/WordPressAdapter.php';

function MIGRATOR_adminMessage($text, $type = 'info')
{
    $title = ($type === 'error') ? 'Error' : 'Migrator';

    return COM_showMessageText(MIGRATOR_escape($text), $title);
}

function MIGRATOR_recordJob($filename, array $result, array $detected, $sourceCms, array $entities)
{
    global $_TABLES;

    $now = date('Y-m-d H:i:s');
    $cms = DB_escapeString($sourceCms);
    $version = ($detected['cms'] === $sourceCms) ? $detected['version'] : '';
    $version = DB_escapeString($version);
    $file = DB_escapeString($filename);
    $tableMap = DB_escapeString(json_encode($result['table_map']));
    $report = DB_escapeString(json_encode(array(
        'tables' => count($result['source_tables']),
        'executed' => $result['executed'],
        'skipped' => $result['skipped'],
        'detected_cms' => $detected['cms'],
        'entities' => $entities
    )));

    $sql = "INSERT INTO {$_TABLES['migrator_jobs']}
        (source_cms, source_version, source_file, status, table_map, report, created, modified)
        VALUES ('{$cms}', '{$version}', '{$file}', 'staged', '{$tableMap}', '{$report}', '{$now}', '{$now}')";

    return DB_query($sql, 1) !== false;
}

function MIGRATOR_renderJobs()
{
    global $_TABLES, $LANG_MIGRATOR;

    $result = DB_query("SELECT * FROM {$_TABLES['migrator_jobs']} ORDER BY job_id DESC LIMIT 1");

    if (DB_numRows($result) === 0) {
        return '<p class="migrator-muted">' . MIGRATOR_escape($LANG_MIGRATOR['no_jobs']) . '</p>';
    }

    $row = DB_fetchArray($result);
    $labelKey = isset($LANG_MIGRATOR[$row['source_cms']]) ? $row['source_cms'] : 'unknown';
    $version = trim((string) $row['source_version']);

    $html = '<div class="migrator-grid">';
    $html .= '<div class="migrator-metric"><div class="migrator-metric__label">'
        . MIGRATOR_escape($LANG_MIGRATOR['source']) . '</div><div class="migrator-metric__value">'
        . MIGRATOR_escape($LANG_MIGRATOR[$labelKey]) . '</div></div>';
    $html .= '<div class="migrator-metric"><div class="migrator-metric__label">'
        . MIGRATOR_escape($LANG_MIGRATOR['status']) . '</div><div class="migrator-metric__value">'
        . '<span class="migrator-status">' . MIGRATOR_escape($row['status']) . '</span></div></div>';
    $html .= '<div class="migrator-metric"><div class="migrator-metric__label">'
        . MIGRATOR_escape($LANG_MIGRATOR['created']) . '</div><div class="migrator-metric__value">'
        . MIGRATOR_escape($row['created']) . '</div></div>';

    if ($version !== '') {
        $html .= '<div class="migrator-metric"><div class="migrator-metric__label">'
            . MIGRATOR_escape($LANG_MIGRATOR['version']) . '</div><div class="migrator-metric__value">'
            . MIGRATOR_escape($version) . '</div></div>';
    }

    $html .= '</div>';

    return $html;
}

function MIGRATOR_renderLatestAnalysis()
{
    global $_TABLES, $LANG_MIGRATOR;

    $result = DB_query("SELECT * FROM {$_TABLES['migrator_jobs']} ORDER BY job_id DESC LIMIT 1");
    if (DB_numRows($result) === 0) {
        return '<p class="migrator-muted">' . MIGRATOR_escape($LANG_MIGRATOR['no_jobs']) . '</p>';
    }

    $row = DB_fetchArray($result);
    $report = json_decode($row['report'], true);
    if (!is_array($report)) {
        $report = array();
    }

    $labelKey = isset($LANG_MIGRATOR[$row['source_cms']]) ? $row['source_cms'] : 'unknown';
    $selectedLabel = $LANG_MIGRATOR[$labelKey];

    $html = '<div class="migrator-grid">';
    $html .= '<div class="migrator-metric"><div class="migrator-metric__label">'
        . MIGRATOR_escape($LANG_MIGRATOR['source']) . '</div><div class="migrator-metric__value">'
        . MIGRATOR_escape($selectedLabel) . '</div></div>';
    $html .= '<div class="migrator-metric"><div class="migrator-metric__label">'
        . MIGRATOR_escape($LANG_MIGRATOR['table_count']) . '</div><div class="migrator-metric__value">'
        . (int) (isset($report['tables']) ? $report['tables'] : 0) . '</div></div>';
    $html .= '<div class="migrator-metric"><div class="migrator-metric__label">'
        . MIGRATOR_escape($LANG_MIGRATOR['statement_count']) . '</div><div class="migrator-metric__value">'
        . (int) (isset($report['executed']) ? $report['executed'] : 0) . '</div></div>';
    $html .= '<div class="migrator-metric"><div class="migrator-metric__label">'
        . MIGRATOR_escape($LANG_MIGRATOR['skipped_count']) . '</div><div class="migrator-metric__value">'
        . (int) (isset($report['skipped']) ? $report['skipped'] : 0) . '</div></div>';
    $html .= '</div>';

    if (!empty($report['detected_cms'])
        && $report['detected_cms'] !== 'unknown'
        && $report['detected_cms'] !== $row['source_cms']
    ) {
        $detectedKey = isset($LANG_MIGRATOR[$report['detected_cms']]) ? $report['detected_cms'] : 'unknown';
        $html .= '<div class="migrator-warning"><strong>'
            . MIGRATOR_escape($LANG_MIGRATOR['cms_detected']) . ':</strong> '
            . MIGRATOR_escape($LANG_MIGRATOR[$detectedKey]) . '</div>';
    }

    if (!empty($report['entities']) && is_array($report['entities'])) {
        $html .= '<h3>' . MIGRATOR_escape($LANG_MIGRATOR['recoverable_content']) . '</h3>';
        $html .= '<div class="migrator-table-wrap"><table class="admin-list"><thead><tr>';
        $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['content_type']) . '</th>';
        $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['records']) . '</th>';
        $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['support_status']) . '</th>';
        $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['required_plugin']) . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($report['entities'] as $entity) {
            if (empty($entity['table'])) {
                continue;
            }

            $status = isset($entity['status']) ? $entity['status'] : 'planned';
            $statusLabel = isset($LANG_MIGRATOR['status_' . $status])
                ? $LANG_MIGRATOR['status_' . $status]
                : $status;
            $requiredPlugin = isset($entity['required_plugin']) ? (string) $entity['required_plugin'] : '';

            if ($requiredPlugin === '') {
                $pluginStatus = $LANG_MIGRATOR['plugin_not_required'];
            } elseif (!empty($entity['plugin_active'])) {
                $pluginStatus = $requiredPlugin . ' — ' . $LANG_MIGRATOR['plugin_ready'];
            } else {
                $pluginStatus = $requiredPlugin . ' — ' . $LANG_MIGRATOR['plugin_missing'];
            }

            $html .= '<tr>';
            $html .= '<td><strong>' . MIGRATOR_escape($entity['label']) . '</strong></td>';
            $html .= '<td>' . (int) $entity['count'] . '</td>';
            $html .= '<td>' . MIGRATOR_escape($statusLabel) . '</td>';
            $html .= '<td>' . MIGRATOR_escape($pluginStatus) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
    }

    $html .= '<p class="migrator-muted"><strong>' . MIGRATOR_escape($LANG_MIGRATOR['next']) . ':</strong> '
        . MIGRATOR_escape($LANG_MIGRATOR['next_text']) . '</p>';

    return $html;
}

function MIGRATOR_loadJob($jobId)
{
    global $_TABLES;

    $jobId = (int) $jobId;
    if ($jobId <= 0) {
        return null;
    }

    $result = DB_query("SELECT * FROM {$_TABLES['migrator_jobs']} WHERE job_id = {$jobId} LIMIT 1");
    if (DB_numRows($result) === 0) {
        return null;
    }

    return DB_fetchArray($result);
}

function MIGRATOR_updateJobReport($jobId, array $report, $status)
{
    global $_TABLES;

    $jobId = (int) $jobId;
    $reportJson = DB_escapeString(json_encode($report));
    $status = DB_escapeString($status);
    $now = DB_escapeString(date('Y-m-d H:i:s'));

    DB_query("UPDATE {$_TABLES['migrator_jobs']}
        SET report = '{$reportJson}', status = '{$status}', modified = '{$now}'
        WHERE job_id = {$jobId}");
}

function MIGRATOR_runCoreJob($jobId, $dryRun)
{
    $job = MIGRATOR_loadJob($jobId);
    if (!is_array($job)) {
        throw new RuntimeException('Migration job not found.');
    }

    if (!in_array($job['source_cms'], array('legacy_geeklog', 'glfusion', 'wordpress'), true)) {
        throw new RuntimeException('No migration adapter is available for this source.');
    }

    $tableMap = json_decode($job['table_map'], true);
    $report = json_decode($job['report'], true);

    if (!is_array($tableMap)) {
        throw new RuntimeException('The staged table map is invalid.');
    }
    if (!is_array($report)) {
        $report = array();
    }

    if (!$dryRun && !MIGRATOR_prepareFreshDestination($job['source_cms'], $tableMap)) {
        throw new RuntimeException('Destination is no longer a fresh Geeklog installation.');
    }

    $entities = array('users', 'topics', 'stories', 'comments', 'staticpages');

    if ($job['source_cms'] === 'glfusion') {
        $adapter = new MigratorGlfusionAdapter((int) $jobId, $tableMap, $dryRun);
        $migration = $adapter->run($entities);
    } elseif ($job['source_cms'] === 'wordpress') {
        $adapter = new MigratorWordPressAdapter((int) $jobId, $tableMap, $dryRun);
        $migration = $adapter->run();
    } else {
        $adapter = new MigratorLegacyGeeklogAdapter((int) $jobId, $tableMap, $dryRun);
        $migration = $adapter->run($entities);
    }

    if ($dryRun) {
        $report['dry_run'] = $migration;
        MIGRATOR_updateJobReport($jobId, $report, 'dry-run');
    } else {
        $report['migration'] = $migration;
        MIGRATOR_updateJobReport($jobId, $report, 'migrated');
    }

    return $migration;
}

function MIGRATOR_renderMigrationResult(array $migration, $title)
{
    global $LANG_MIGRATOR;

    $html = '<h3>' . MIGRATOR_escape($title) . '</h3>';

    if (!empty($migration['warnings'])) {
        $html .= '<ul>';
        foreach ($migration['warnings'] as $warning) {
            $html .= '<li>' . MIGRATOR_escape($warning) . '</li>';
        }
        $html .= '</ul>';
    }

    if (empty($migration['entities']) || !is_array($migration['entities'])) {
        return $html . '<p>' . MIGRATOR_escape($LANG_MIGRATOR['no_migration_result']) . '</p>';
    }

    $html .= '<div class="migrator-table-wrap"><table class="admin-list"><thead><tr>';
    $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['content_type']) . '</th>';
    $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['would_import']) . '</th>';
    $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['imported']) . '</th>';
    $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['preserved']) . '</th>';
    $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['conflicts']) . '</th>';
    $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['skipped']) . '</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($migration['entities'] as $entity => $stats) {
        $html .= '<tr>';
        $html .= '<td>' . MIGRATOR_escape($entity) . '</td>';
        $html .= '<td>' . (int) (isset($stats['would_import']) ? $stats['would_import'] : 0) . '</td>';
        $html .= '<td>' . (int) (isset($stats['imported']) ? $stats['imported'] : 0) . '</td>';
        $html .= '<td>' . (int) (isset($stats['preserved']) ? $stats['preserved'] : 0) . '</td>';
        $html .= '<td>' . (int) (isset($stats['conflicts']) ? $stats['conflicts'] : 0) . '</td>';
        $html .= '<td>' . (int) (isset($stats['skipped']) ? $stats['skipped'] : 0) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    if (!empty($migration['plugins']) && is_array($migration['plugins'])) {
        $html .= '<h4>' . MIGRATOR_escape($LANG_MIGRATOR['plugin_content']) . '</h4>';

        foreach ($migration['plugins'] as $plugin => $pluginReport) {
            $html .= '<h5>' . MIGRATOR_escape(ucfirst($plugin)) . '</h5>';

            if (empty($pluginReport['available'])) {
                $html .= '<p>' . MIGRATOR_escape($LANG_MIGRATOR['source_not_detected']) . '</p>';
                continue;
            }

            if (!empty($pluginReport['tables']) && is_array($pluginReport['tables'])) {
                $html .= '<div class="migrator-table-wrap"><table class="admin-list"><thead><tr>';
                $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['source_table']) . '</th>';
                $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['would_import']) . '</th>';
                $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['imported']) . '</th>';
                $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['conflicts']) . '</th>';
                $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['skipped']) . '</th>';
                $html .= '</tr></thead><tbody>';

                foreach ($pluginReport['tables'] as $table => $stats) {
                    $html .= '<tr>';
                    $html .= '<td>' . MIGRATOR_escape($table) . '</td>';
                    $html .= '<td>' . (int) (isset($stats['would_import']) ? $stats['would_import'] : 0) . '</td>';
                    $html .= '<td>' . (int) (isset($stats['imported']) ? $stats['imported'] : 0) . '</td>';
                    $html .= '<td>' . (int) (isset($stats['conflicts']) ? $stats['conflicts'] : 0) . '</td>';
                    $html .= '<td>' . (int) (isset($stats['skipped']) ? $stats['skipped'] : 0) . '</td>';
                    $html .= '</tr>';
                }

                $html .= '</tbody></table></div>';
            }

            if (!empty($pluginReport['warnings'])) {
                $html .= '<ul>';
                foreach ($pluginReport['warnings'] as $warning) {
                    $html .= '<li>' . MIGRATOR_escape($warning) . '</li>';
                }
                $html .= '</ul>';
            }
        }
    }

    if (!empty($migration['media_manifest']) && is_array($migration['media_manifest'])) {
        $html .= '<h4>' . MIGRATOR_escape($LANG_MIGRATOR['files_to_copy']) . '</h4>';
        $html .= '<div class="migrator-table-wrap"><table class="admin-list"><thead><tr>';
        $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['content_type']) . '</th>';
        $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['source_path']) . '</th>';
        $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['target_path']) . '</th>';
        $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['note']) . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($migration['media_manifest'] as $item) {
            $html .= '<tr>';
            $html .= '<td>' . MIGRATOR_escape(isset($item['type']) ? $item['type'] : '') . '</td>';
            $html .= '<td><code>' . MIGRATOR_escape(isset($item['source']) ? $item['source'] : '') . '</code></td>';
            $html .= '<td><code>' . MIGRATOR_escape(isset($item['target']) ? $item['target'] : '') . '</code></td>';
            $html .= '<td>' . MIGRATOR_escape(isset($item['note']) ? $item['note'] : '') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
    }

    return $html;
}


function MIGRATOR_missingDependencies(array $job)
{
    $missing = array();
    $report = isset($job['report']) ? json_decode($job['report'], true) : array();

    if (!is_array($report) || empty($report['entities']) || !is_array($report['entities'])) {
        return $missing;
    }

    foreach ($report['entities'] as $entity) {
        $count = isset($entity['count']) ? (int) $entity['count'] : 0;
        $plugin = isset($entity['required_plugin']) ? trim((string) $entity['required_plugin']) : '';

        if ($count > 0 && $plugin !== '' && !MIGRATOR_isPluginActive($plugin)) {
            $missing[$plugin] = true;
        }
    }

    return array_keys($missing);
}

function MIGRATOR_renderDependencyWarning(array $missing)
{
    global $LANG_MIGRATOR;

    if (empty($missing)) {
        return '';
    }

    $html = '<div class="migrator-dependency-warning">';
    $html .= '<h3>' . MIGRATOR_escape($LANG_MIGRATOR['missing_dependencies_title']) . '</h3>';
    $html .= '<p><strong>' . MIGRATOR_escape($LANG_MIGRATOR['missing_dependencies_intro']) . '</strong></p>';
    $html .= '<ul>';

    foreach ($missing as $plugin) {
        $html .= '<li><code>' . MIGRATOR_escape($plugin) . '</code> — '
            . MIGRATOR_escape($LANG_MIGRATOR['plugin_missing']) . '</li>';
    }

    $html .= '</ul>';
    $html .= '<p>' . MIGRATOR_escape($LANG_MIGRATOR['missing_dependencies_action']) . '</p>';
    $html .= '</div>';

    return $html;
}

function MIGRATOR_renderMigrationActions()
{
    global $_TABLES, $LANG_MIGRATOR;

    $result = DB_query("SELECT * FROM {$_TABLES['migrator_jobs']} ORDER BY job_id DESC LIMIT 1");
    if (DB_numRows($result) === 0) {
        return '';
    }

    $job = DB_fetchArray($result);
    if (!in_array($job['source_cms'], array('legacy_geeklog', 'glfusion', 'wordpress'), true)) {
        return '<p>' . MIGRATOR_escape($LANG_MIGRATOR['adapter_not_ready']) . '</p>';
    }

    $jobId = (int) $job['job_id'];
    $missingDependencies = MIGRATOR_missingDependencies($job);
    $token1 = SEC_createToken();
    $token2 = SEC_createToken();

    $html = MIGRATOR_renderDependencyWarning($missingDependencies);
    $html .= '<div class="migrator-actions">';
    $html .= '<form method="post" action="' . MIGRATOR_escape(MIGRATOR_adminUrl()) . '">';
    $html .= '<input type="hidden" name="mode" value="dryrun">';
    $html .= '<input type="hidden" name="job_id" value="' . $jobId . '">';
    $html .= '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . MIGRATOR_escape($token1) . '">';
    $html .= '<button type="submit">' . MIGRATOR_escape($LANG_MIGRATOR['run_dry_run']) . '</button>';
    $html .= '</form>';

    if (empty($missingDependencies)) {
        $html .= '<form method="post" action="' . MIGRATOR_escape(MIGRATOR_adminUrl()) . '" onsubmit="return confirm('
            . htmlspecialchars(json_encode($LANG_MIGRATOR['migrate_confirm']), ENT_QUOTES, 'UTF-8') . ');">';
        $html .= '<input type="hidden" name="mode" value="migrate">';
        $html .= '<input type="hidden" name="job_id" value="' . $jobId . '">';
        $html .= '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . MIGRATOR_escape($token2) . '">';
        $html .= '<button type="submit">' . MIGRATOR_escape($LANG_MIGRATOR['run_migration']) . '</button>';
        $html .= '</form>';
    }
    $html .= '</div>';

    $report = json_decode($job['report'], true);
    if (is_array($report) && isset($report['dry_run']) && is_array($report['dry_run'])) {
        $html .= MIGRATOR_renderMigrationResult($report['dry_run'], $LANG_MIGRATOR['dry_run_result']);
    }
    if (is_array($report) && isset($report['migration']) && is_array($report['migration'])) {
        $html .= MIGRATOR_renderMigrationResult($report['migration'], $LANG_MIGRATOR['migration_result']);
    }

    return $html;
}


function MIGRATOR_renderResetForm()
{
    global $LANG_MIGRATOR;

    if (!MIGRATOR_hasCompletedMigration()) {
        return '';
    }

    $token = SEC_createToken();
    $phrase = 'RESET MIGRATOR';

    $html = '<div class="migrator-reset-box">';
    $html .= '<h2>' . MIGRATOR_escape($LANG_MIGRATOR['reset_title']) . '</h2>';
    $html .= '<p><strong>' . MIGRATOR_escape($LANG_MIGRATOR['reset_danger']) . '</strong></p>';
    $html .= '<ul>';
    $html .= '<li>' . MIGRATOR_escape($LANG_MIGRATOR['reset_deletes_content']) . '</li>';
    $html .= '<li>' . MIGRATOR_escape($LANG_MIGRATOR['reset_deletes_users']) . '</li>';
    $html .= '<li>' . MIGRATOR_escape($LANG_MIGRATOR['reset_deletes_plugins']) . '</li>';
    $html .= '<li>' . MIGRATOR_escape($LANG_MIGRATOR['reset_media_warning']) . '</li>';
    $html .= '</ul>';

    $html .= '<form method="post" action="' . MIGRATOR_escape(MIGRATOR_adminUrl()) . '">';
    $html .= '<input type="hidden" name="mode" value="reset">';
    $html .= '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . MIGRATOR_escape($token) . '">';

    $html .= '<p><label>';
    $html .= '<input type="checkbox" name="confirm_understand" value="1" required> ';
    $html .= MIGRATOR_escape($LANG_MIGRATOR['reset_checkbox']);
    $html .= '</label></p>';

    $html .= '<p>' . MIGRATOR_escape($LANG_MIGRATOR['reset_type_prompt']) . ' ';
    $html .= '<code>' . MIGRATOR_escape($phrase) . '</code></p>';

    $html .= '<p><input type="text" name="confirmation_text" value="" autocomplete="off" spellcheck="false" required></p>';
    $html .= '<p><button type="submit">' . MIGRATOR_escape($LANG_MIGRATOR['reset_button']) . '</button></p>';
    $html .= '</form>';
    $html .= '</div>';

    return $html;
}

function MIGRATOR_purgeData()
{
    global $_TABLES;

    MIGRATOR_dropAllStagingTables();
    DB_query("DELETE FROM {$_TABLES['migrator_id_map']}");
    DB_query("DELETE FROM {$_TABLES['migrator_log']}");
    DB_query("DELETE FROM {$_TABLES['migrator_jobs']}");

    $dir = MIGRATOR_dataDir();
    if (is_dir($dir)) {
        $files = glob($dir . '*.sql');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }
}

$message = '';
$requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
$mode = isset($_REQUEST['mode']) ? COM_applyFilter($_REQUEST['mode']) : '';

if ($requestMethod === 'POST') {
    if (!SEC_checkToken()) {
        $message = MIGRATOR_adminMessage($LANG_MIGRATOR['security_error'], 'error');
    } elseif ($mode === 'stage') {
        $allowedCms = array('legacy_geeklog', 'glfusion', 'wordpress');
        $sourceCms = isset($_POST['source_cms']) ? COM_applyFilter($_POST['source_cms']) : '';

        if (!in_array($sourceCms, $allowedCms, true)) {
            $message = MIGRATOR_adminMessage($LANG_MIGRATOR['invalid_source'], 'error');
        } elseif (!MIGRATOR_ensureDataDir()) {
            $message = MIGRATOR_adminMessage($LANG_MIGRATOR['storage_error'], 'error');
        } elseif (!isset($_FILES['sql_dump']) || !is_array($_FILES['sql_dump'])) {
            $message = MIGRATOR_adminMessage($LANG_MIGRATOR['upload_failed'], 'error');
        } else {
            $upload = $_FILES['sql_dump'];

            try {
                $normalized = MigratorDumpUpload::normalize($upload, MIGRATOR_dataDir());

                $importer = new MigratorSqlDumpImporter();
                $result = $importer->import($normalized['sql_path']);
                $detected = MigratorSourceDetector::detect($result['source_tables'], $result['sample']);
                $entities = MigratorMigrationAnalyzer::analyse($sourceCms, $result['table_map']);

                if (!MIGRATOR_recordJob($normalized['stored_name'], $result, $detected, $sourceCms, $entities)) {
                    throw new RuntimeException($LANG_MIGRATOR['database_error']);
                }

                $message = MIGRATOR_adminMessage(
                    $LANG_MIGRATOR['stage_success'] . ' '
                    . sprintf($LANG_MIGRATOR['normalized_format'], strtoupper($normalized['format']))
                );
            } catch (Exception $e) {
                COM_errorLog('Migrator staging failed: ' . $e->getMessage());
                $message = MIGRATOR_adminMessage(
                    $LANG_MIGRATOR['stage_failed'] . ' ' . $e->getMessage(),
                    'error'
                );
            }
        }
    } elseif ($mode === 'dryrun') {
        $jobId = isset($_POST['job_id']) ? (int) $_POST['job_id'] : 0;
        try {
            MIGRATOR_runCoreJob($jobId, true);
            $message = MIGRATOR_adminMessage($LANG_MIGRATOR['dry_run_complete']);
        } catch (Exception $e) {
            COM_errorLog('Migrator dry run failed: ' . $e->getMessage());
            $message = MIGRATOR_adminMessage($LANG_MIGRATOR['dry_run_failed'] . ' ' . $e->getMessage(), 'error');
        }
    } elseif ($mode === 'migrate') {
        $destination = MIGRATOR_destinationStatus();
        $jobId = isset($_POST['job_id']) ? (int) $_POST['job_id'] : 0;
        $job = MIGRATOR_loadJob($jobId);
        $missingDependencies = is_array($job) ? MIGRATOR_missingDependencies($job) : array();

        if (!is_array($job)) {
            $message = MIGRATOR_adminMessage($LANG_MIGRATOR['migration_failed'] . ' Migration job not found.', 'error');
        } elseif (!empty($missingDependencies)) {
            $message = MIGRATOR_adminMessage($LANG_MIGRATOR['migration_blocked_dependencies'], 'error');
        } elseif (!$destination['fresh']) {
            $message = MIGRATOR_adminMessage($LANG_MIGRATOR['destination_not_fresh'], 'error');
        } else {
            try {
                MIGRATOR_runCoreJob($jobId, false);
                $message = MIGRATOR_adminMessage($LANG_MIGRATOR['migration_complete']);
            } catch (Exception $e) {
                COM_errorLog('Migrator migration failed: ' . $e->getMessage());
                $message = MIGRATOR_adminMessage($LANG_MIGRATOR['migration_failed'] . ' ' . $e->getMessage(), 'error');
            }
        }
    } elseif ($mode === 'reset') {
        $confirmed = isset($_POST['confirm_understand']) && $_POST['confirm_understand'] === '1';
        $typed = isset($_POST['confirmation_text']) ? trim((string) $_POST['confirmation_text']) : '';

        if (!$confirmed || !hash_equals('RESET MIGRATOR', $typed)) {
            $message = MIGRATOR_adminMessage($LANG_MIGRATOR['reset_confirmation_failed'], 'error');
        } elseif (!MIGRATOR_hasCompletedMigration()) {
            $message = MIGRATOR_adminMessage($LANG_MIGRATOR['reset_unavailable'], 'error');
        } elseif (!MIGRATOR_resetTestInstallation()) {
            $message = MIGRATOR_adminMessage($LANG_MIGRATOR['reset_failed'], 'error');
        } else {
            MIGRATOR_purgeData();
            $message = MIGRATOR_adminMessage($LANG_MIGRATOR['reset_complete']);
        }
    } elseif ($mode === 'purge') {
        MIGRATOR_purgeData();
        $message = MIGRATOR_adminMessage($LANG_MIGRATOR['purged']);
    }
}

$destination = MIGRATOR_destinationStatus();
$destinationMessage = '<p class="' . ($destination['fresh'] ? 'migrator-ok' : 'migrator-warning') . '">'
    . '<span class="migrator-status">'
    . MIGRATOR_escape($destination['fresh'] ? $LANG_MIGRATOR['destination_ready'] : $LANG_MIGRATOR['destination_blocked'])
    . '</span> '
    . MIGRATOR_escape($destination['fresh'] ? $LANG_MIGRATOR['destination_fresh'] : $LANG_MIGRATOR['destination_not_fresh'])
    . '</p>';
$destinationMessage .= '<div class="migrator-grid">';
foreach (array('users', 'stories', 'topics', 'comments', 'staticpages') as $countKey) {
    $destinationMessage .= '<div class="migrator-metric"><div class="migrator-metric__label">'
        . MIGRATOR_escape(isset($LANG_MIGRATOR['count_' . $countKey]) ? $LANG_MIGRATOR['count_' . $countKey] : $countKey)
        . '</div><div class="migrator-metric__value">'
        . (int) $destination['counts'][$countKey]
        . '</div></div>';
}
$destinationMessage .= '</div>';

$token = SEC_createToken();

$uploadForm = '<form method="post" enctype="multipart/form-data" action="' . MIGRATOR_escape(MIGRATOR_adminUrl()) . '">';
$uploadForm .= '<input type="hidden" name="mode" value="stage">';
$uploadForm .= '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . MIGRATOR_escape($token) . '">';
$uploadForm .= '<div class="migrator-upload-grid">';
$uploadForm .= '<div><label for="source_cms"><strong>' . MIGRATOR_escape($LANG_MIGRATOR['choose_source']) . '</strong></label><br>';
$uploadForm .= '<select name="source_cms" id="source_cms" required>';
$uploadForm .= '<option value="">' . MIGRATOR_escape($LANG_MIGRATOR['choose_source_placeholder']) . '</option>';
$uploadForm .= '<option value="legacy_geeklog">' . MIGRATOR_escape($LANG_MIGRATOR['legacy_geeklog']) . '</option>';
$uploadForm .= '<option value="glfusion">' . MIGRATOR_escape($LANG_MIGRATOR['glfusion']) . '</option>';
$uploadForm .= '<option value="wordpress">' . MIGRATOR_escape($LANG_MIGRATOR['wordpress']) . '</option>';
$uploadForm .= '</select></div>';
$uploadForm .= '<div><label for="sql_dump"><strong>' . MIGRATOR_escape($LANG_MIGRATOR['sql_file']) . '</strong></label><br>';
$uploadForm .= '<input type="file" name="sql_dump" id="sql_dump" accept=".sql,.sql.gz,.gz,.zip,application/sql,text/plain,application/gzip,application/zip" required></div>';
$uploadForm .= '</div>';
$uploadForm .= '<p class="migrator-muted">' . MIGRATOR_escape($LANG_MIGRATOR['accepted_formats']) . '</p>';
$uploadForm .= '<div class="migrator-actions"><button type="submit">' . MIGRATOR_escape($LANG_MIGRATOR['import_stage']) . '</button></div>';
$uploadForm .= '</form>';

$purgeToken = SEC_createToken();
$purgeForm = '<form method="post" action="' . MIGRATOR_escape(MIGRATOR_adminUrl()) . '" onsubmit="return confirm('
    . htmlspecialchars(json_encode($LANG_MIGRATOR['purge_confirm']), ENT_QUOTES, 'UTF-8') . ');">';
$purgeForm .= '<input type="hidden" name="mode" value="purge">';
$purgeForm .= '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . MIGRATOR_escape($purgeToken) . '">';
$purgeForm .= '<button type="submit">' . MIGRATOR_escape($LANG_MIGRATOR['purge']) . '</button>';
$purgeForm .= '</form>';

$template = COM_newTemplate($_CONF['path'] . 'plugins/migrator/templates');
$template->set_file('admin', 'admin.thtml');
$template->set_var(array(
    'page_title' => MIGRATOR_escape($LANG_MIGRATOR['title']),
    'intro' => MIGRATOR_escape($LANG_MIGRATOR['intro']),
    'message' => $message,
    'getting_started' => MIGRATOR_escape($LANG_MIGRATOR['getting_started']),
    'step_1' => MIGRATOR_escape($LANG_MIGRATOR['step_1']),
    'step_2' => MIGRATOR_escape($LANG_MIGRATOR['step_2']),
    'step_3' => MIGRATOR_escape($LANG_MIGRATOR['step_3']),
    'fresh_warning' => MIGRATOR_escape($LANG_MIGRATOR['fresh_warning']),
    'destination_title' => MIGRATOR_escape($LANG_MIGRATOR['destination_title']),
    'safe_stage' => MIGRATOR_escape($LANG_MIGRATOR['safe_stage']),
    'destination_status' => $destinationMessage,
    'upload_title' => MIGRATOR_escape($LANG_MIGRATOR['upload']),
    'upload_form' => $uploadForm,
    'jobs_title' => MIGRATOR_escape($LANG_MIGRATOR['jobs']),
    'jobs_table' => MIGRATOR_renderJobs(),
    'analysis_title' => MIGRATOR_escape($LANG_MIGRATOR['analysis']),
    'analysis' => MIGRATOR_renderLatestAnalysis(),
    'migration_actions' => MIGRATOR_renderMigrationActions(),
    'reset_form' => MIGRATOR_renderResetForm(),
    'purge_form' => $purgeForm
));

$content = $template->finish($template->parse('output', 'admin'));

echo COM_createHTMLDocument($content, array('pagetitle' => $LANG_MIGRATOR['title']));
