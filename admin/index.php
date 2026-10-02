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
require_once $_CONF['path'] . 'plugins/migrator/classes/SourceDetector.php';
require_once $_CONF['path'] . 'plugins/migrator/classes/MigrationAnalyzer.php';

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

    $result = DB_query("SELECT * FROM {$_TABLES['migrator_jobs']} ORDER BY job_id DESC");

    if (DB_numRows($result) === 0) {
        return '<p>' . MIGRATOR_escape($LANG_MIGRATOR['no_jobs']) . '</p>';
    }

    $html = '<div class="migrator-table-wrap"><table class="admin-list"><thead><tr>';
    $html .= '<th>ID</th>';
    $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['source']) . '</th>';
    $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['version']) . '</th>';
    $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['status']) . '</th>';
    $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['created']) . '</th>';
    $html .= '</tr></thead><tbody>';

    while ($row = DB_fetchArray($result)) {
        $labelKey = isset($LANG_MIGRATOR[$row['source_cms']]) ? $row['source_cms'] : 'unknown';
        $html .= '<tr>';
        $html .= '<td>' . (int) $row['job_id'] . '</td>';
        $html .= '<td>' . MIGRATOR_escape($LANG_MIGRATOR[$labelKey]) . '</td>';
        $html .= '<td>' . MIGRATOR_escape($row['source_version']) . '</td>';
        $html .= '<td>' . MIGRATOR_escape($row['status']) . '</td>';
        $html .= '<td>' . MIGRATOR_escape($row['created']) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    return $html;
}

function MIGRATOR_renderLatestAnalysis()
{
    global $_TABLES, $LANG_MIGRATOR;

    $result = DB_query("SELECT * FROM {$_TABLES['migrator_jobs']} ORDER BY job_id DESC LIMIT 1");
    if (DB_numRows($result) === 0) {
        return '<p>' . MIGRATOR_escape($LANG_MIGRATOR['no_jobs']) . '</p>';
    }

    $row = DB_fetchArray($result);
    $report = json_decode($row['report'], true);
    if (!is_array($report)) {
        $report = array();
    }

    $labelKey = isset($LANG_MIGRATOR[$row['source_cms']]) ? $row['source_cms'] : 'unknown';

    $html = '<dl class="migrator-analysis">';
    $html .= '<dt>' . MIGRATOR_escape($LANG_MIGRATOR['cms_selected']) . '</dt><dd>'
        . MIGRATOR_escape($LANG_MIGRATOR[$labelKey]) . '</dd>';

    if (!empty($report['detected_cms']) && $report['detected_cms'] !== 'unknown') {
        $detectedKey = isset($LANG_MIGRATOR[$report['detected_cms']]) ? $report['detected_cms'] : 'unknown';
        $html .= '<dt>' . MIGRATOR_escape($LANG_MIGRATOR['cms_detected']) . '</dt><dd>'
            . MIGRATOR_escape($LANG_MIGRATOR[$detectedKey]) . '</dd>';
    }

    $html .= '<dt>' . MIGRATOR_escape($LANG_MIGRATOR['table_count']) . '</dt><dd>'
        . (int) (isset($report['tables']) ? $report['tables'] : 0) . '</dd>';
    $html .= '<dt>' . MIGRATOR_escape($LANG_MIGRATOR['statement_count']) . '</dt><dd>'
        . (int) (isset($report['executed']) ? $report['executed'] : 0) . '</dd>';
    $html .= '<dt>' . MIGRATOR_escape($LANG_MIGRATOR['skipped_count']) . '</dt><dd>'
        . (int) (isset($report['skipped']) ? $report['skipped'] : 0) . '</dd>';
    $html .= '</dl>';

    if (!empty($report['entities']) && is_array($report['entities'])) {
        $html .= '<h3>' . MIGRATOR_escape($LANG_MIGRATOR['recoverable_content']) . '</h3>';
        $html .= '<div class="migrator-table-wrap"><table class="admin-list"><thead><tr>';
        $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['content_type']) . '</th>';
        $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['records']) . '</th>';
        $html .= '<th>' . MIGRATOR_escape($LANG_MIGRATOR['support_status']) . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($report['entities'] as $entity) {
            if (empty($entity['table'])) {
                continue;
            }

            $status = isset($entity['status']) ? $entity['status'] : 'planned';
            $statusLabel = isset($LANG_MIGRATOR['status_' . $status])
                ? $LANG_MIGRATOR['status_' . $status]
                : $status;

            $html .= '<tr>';
            $html .= '<td>' . MIGRATOR_escape($entity['label']) . '</td>';
            $html .= '<td>' . (int) $entity['count'] . '</td>';
            $html .= '<td>' . MIGRATOR_escape($statusLabel) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
    }

    $html .= '<p><strong>' . MIGRATOR_escape($LANG_MIGRATOR['next']) . ':</strong> '
        . MIGRATOR_escape($LANG_MIGRATOR['next_text']) . '</p>';

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
            $error = isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;
            $name = isset($upload['name']) ? basename($upload['name']) : '';

            if ($error !== UPLOAD_ERR_OK) {
                $message = MIGRATOR_adminMessage($LANG_MIGRATOR['upload_failed'], 'error');
            } elseif (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'sql') {
                $message = MIGRATOR_adminMessage($LANG_MIGRATOR['invalid_file'], 'error');
            } else {
                $storedName = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.sql';
                $destination = MIGRATOR_dataDir() . $storedName;

                if (!move_uploaded_file($upload['tmp_name'], $destination)) {
                    $message = MIGRATOR_adminMessage($LANG_MIGRATOR['upload_failed'], 'error');
                } else {
                    try {
                        $importer = new MigratorSqlDumpImporter();
                        $result = $importer->import($destination);
                        $detected = MigratorSourceDetector::detect($result['source_tables'], $result['sample']);
                        $entities = MigratorMigrationAnalyzer::analyse($sourceCms, $result['table_map']);

                        if (!MIGRATOR_recordJob($storedName, $result, $detected, $sourceCms, $entities)) {
                            throw new RuntimeException($LANG_MIGRATOR['database_error']);
                        }

                        $message = MIGRATOR_adminMessage($LANG_MIGRATOR['stage_success']);
                    } catch (Exception $e) {
                        COM_errorLog('Migrator staging failed: ' . $e->getMessage());
                        $message = MIGRATOR_adminMessage($LANG_MIGRATOR['stage_failed'] . ' ' . $e->getMessage(), 'error');
                    }
                }
            }
        }
    } elseif ($mode === 'purge') {
        MIGRATOR_purgeData();
        $message = MIGRATOR_adminMessage($LANG_MIGRATOR['purged']);
    }
}

$destination = MIGRATOR_destinationStatus();
$destinationMessage = $destination['fresh']
    ? '<p><strong>' . MIGRATOR_escape($LANG_MIGRATOR['destination_fresh']) . '</strong></p>'
    : '<p><strong>' . MIGRATOR_escape($LANG_MIGRATOR['destination_not_fresh']) . '</strong></p>';
$destinationMessage .= '<p>' . MIGRATOR_escape($LANG_MIGRATOR['destination_counts']) . ': '
    . 'users=' . (int) $destination['counts']['users'] . ', '
    . 'stories=' . (int) $destination['counts']['stories'] . ', '
    . 'topics=' . (int) $destination['counts']['topics'] . '</p>';

$token = SEC_createToken();

$uploadForm = '<form method="post" enctype="multipart/form-data" action="' . MIGRATOR_escape(MIGRATOR_adminUrl()) . '">';
$uploadForm .= '<input type="hidden" name="mode" value="stage">';
$uploadForm .= '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . MIGRATOR_escape($token) . '">';
$uploadForm .= '<p><label for="source_cms"><strong>' . MIGRATOR_escape($LANG_MIGRATOR['choose_source']) . '</strong></label><br>';
$uploadForm .= '<select name="source_cms" id="source_cms" required>';
$uploadForm .= '<option value="">' . MIGRATOR_escape($LANG_MIGRATOR['choose_source_placeholder']) . '</option>';
$uploadForm .= '<option value="legacy_geeklog">' . MIGRATOR_escape($LANG_MIGRATOR['legacy_geeklog']) . '</option>';
$uploadForm .= '<option value="glfusion">' . MIGRATOR_escape($LANG_MIGRATOR['glfusion']) . '</option>';
$uploadForm .= '<option value="wordpress">' . MIGRATOR_escape($LANG_MIGRATOR['wordpress']) . '</option>';
$uploadForm .= '</select></p>';
$uploadForm .= '<p><label for="sql_dump"><strong>' . MIGRATOR_escape($LANG_MIGRATOR['sql_file']) . '</strong></label><br>';
$uploadForm .= '<input type="file" name="sql_dump" id="sql_dump" accept=".sql,text/plain" required></p>';
$uploadForm .= '<p><button type="submit">' . MIGRATOR_escape($LANG_MIGRATOR['import_stage']) . '</button></p>';
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
    'safe_stage' => MIGRATOR_escape($LANG_MIGRATOR['safe_stage']),
    'destination_status' => $destinationMessage,
    'upload_title' => MIGRATOR_escape($LANG_MIGRATOR['upload']),
    'upload_form' => $uploadForm,
    'jobs_title' => MIGRATOR_escape($LANG_MIGRATOR['jobs']),
    'jobs_table' => MIGRATOR_renderJobs(),
    'analysis_title' => MIGRATOR_escape($LANG_MIGRATOR['analysis']),
    'analysis' => MIGRATOR_renderLatestAnalysis(),
    'purge_form' => $purgeForm
));

$content = $template->finish($template->parse('output', 'admin'));

echo COM_createHTMLDocument($content, array('pagetitle' => $LANG_MIGRATOR['title']));
