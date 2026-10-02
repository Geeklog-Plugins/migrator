<?php

require_once __DIR__ . '/LegacyGeeklogAdapter.php';

/**
 * glFusion core content is close enough to Geeklog to share the core adapter.
 * Forum and MediaGallery are handled here with explicit table mappings.
 */
class MigratorGlfusionAdapter extends MigratorLegacyGeeklogAdapter
{
    public function run(array $entities)
    {
        global $_TABLES;

        $result = parent::run($entities);

        $result['plugins'] = array();
        $result['media_manifest'] = array();

        $result['plugins']['forum'] = $this->migrateForum();
        $result['plugins']['mediagallery'] = $this->migrateMediaGallery();

        if (!empty($result['plugins']['forum']['warnings'])) {
            foreach ($result['plugins']['forum']['warnings'] as $warning) {
                $result['warnings'][] = $warning;
            }
        }
        if (!empty($result['plugins']['mediagallery']['warnings'])) {
            foreach ($result['plugins']['mediagallery']['warnings'] as $warning) {
                $result['warnings'][] = $warning;
            }
        }

        if ($this->sourceTable('mg_media') !== '') {
            $result['media_manifest'][] = array(
                'type' => 'MediaGallery',
                'source' => 'public_html/mediagallery/mediaobjects/',
                'target' => 'public_html/mediagallery/mediaobjects/',
                'note' => 'Copy the complete mediaobjects directory after database migration.'
            );
        }

        if ($this->sourceTable('ff_attachments') !== '') {
            $result['media_manifest'][] = array(
                'type' => 'Forum attachments',
                'source' => 'glFusion forum attachment storage',
                'target' => '',
                'note' => 'The current Geeklog Forum schema has no direct ff_attachments table equivalent; preserve these files separately until a dedicated attachment converter exists.'
            );
        }

        return $result;
    }

    private function migrateForum()
    {
        global $_TABLES;

        $sourceTopic = $this->sourceTable('ff_topic');
        if ($sourceTopic === '') {
            return array(
                'available' => false,
                'warnings' => array(),
                'tables' => array()
            );
        }

        $map = array(
            'ff_categories' => array('target' => 'forum_categories', 'id' => 'id'),
            'ff_forums' => array('target' => 'forum_forums', 'id' => 'forum_id'),
            'ff_topic' => array('target' => 'forum_topic', 'id' => 'id'),
            'ff_log' => array('target' => 'forum_log', 'id' => ''),
            'ff_moderators' => array('target' => 'forum_moderators', 'id' => 'mod_id'),
            'ff_userprefs' => array('target' => 'forum_userprefs', 'id' => 'uid'),
            'ff_banned_ip' => array('target' => 'forum_banned_ip', 'id' => ''),
            'ff_userinfo' => array('target' => 'forum_userinfo', 'id' => 'uid')
        );

        $report = array(
            'available' => true,
            'warnings' => array(),
            'tables' => array()
        );

        foreach ($map as $sourceSuffix => $definition) {
            if (!isset($_TABLES[$definition['target']])) {
                $report['warnings'][] = 'Forum destination table is unavailable: ' . $definition['target'];
                continue;
            }

            $report['tables'][$sourceSuffix] = $this->migratePluginTable(
                $sourceSuffix,
                $_TABLES[$definition['target']],
                'forum:' . $sourceSuffix,
                $definition['id']
            );
        }

        foreach (array('ff_attachments', 'ff_bookmarks', 'ff_rating_assoc') as $unsupported) {
            $source = $this->sourceTable($unsupported);
            if ($source !== '') {
                $count = $this->countRows($source);
                $report['warnings'][] = $unsupported . ': ' . $count
                    . ' source records detected but no direct Geeklog Forum destination table exists.';
            }
        }

        return $report;
    }

    private function migrateMediaGallery()
    {
        global $_TABLES;

        if ($this->sourceTable('mg_albums') === '') {
            return array(
                'available' => false,
                'warnings' => array(),
                'tables' => array()
            );
        }

        $tables = array(
            'mg_albums' => 'album_id',
            'mg_media' => 'media_id',
            'mg_media_albums' => '',
            'mg_mediaqueue' => 'media_id',
            'mg_media_album_queue' => '',
            'mg_playback_options' => '',
            'mg_usage_tracking' => '',
            'mg_userprefs' => 'uid',
            'mg_watermarks' => 'wm_id',
            'mg_category' => 'cat_id',
            'mg_sessions' => 'session_id',
            'mg_session_items' => 'id',
            'mg_session_log' => '',
            'mg_sort' => '',
            'mg_rating' => 'id',
            'mg_exif_tags' => 'name'
        );

        $report = array(
            'available' => true,
            'warnings' => array(),
            'tables' => array()
        );

        foreach ($tables as $tableKey => $idField) {
            $source = $this->sourceTable($tableKey);
            if ($source === '') {
                continue;
            }

            if (!isset($_TABLES[$tableKey])) {
                $report['warnings'][] = 'MediaGallery destination table is unavailable: ' . $tableKey;
                continue;
            }

            $report['tables'][$tableKey] = $this->migratePluginTable(
                $tableKey,
                $_TABLES[$tableKey],
                'mediagallery:' . $tableKey,
                $idField
            );
        }

        if (isset($_TABLES['mg_albums']) && $this->sourceTable('mg_albums') !== '') {
            $sourceColumns = $this->columns($this->sourceTable('mg_albums'));
            $targetColumns = $this->columns($_TABLES['mg_albums']);

            if (isset($sourceColumns['opacity']) && isset($targetColumns['wm_opacity'])) {
                $report['warnings'][] = 'MediaGallery album field opacity was renamed to wm_opacity; values are not automatically remapped yet.';
            }
        }

        return $report;
    }

    private function migratePluginTable($sourceSuffix, $targetTable, $entityType, $idField = '')
    {
        $source = $this->sourceTable($sourceSuffix);
        if ($source === '') {
            return $this->emptyResult('Source table not found.');
        }

        $rows = $this->fetchRows($source);
        $stats = $this->stats();

        foreach ($rows as $row) {
            $sourceId = '';
            if ($idField !== '' && isset($row[$idField])) {
                $sourceId = (string) $row[$idField];
            }

            if ($idField !== '' && $sourceId !== '') {
                $existing = DB_count($targetTable, $idField, DB_escapeString($sourceId));
                if ($existing > 0) {
                    ++$stats['conflicts'];
                    continue;
                }
            }

            $required = ($idField !== '' && array_key_exists($idField, $row))
                ? array($idField)
                : array();

            $copy = $this->copyIntersectionRow($source, $targetTable, $row, $required);
            if ($copy['ok']) {
                ++$stats[$this->dryRun ? 'would_import' : 'imported'];

                if (!$this->dryRun && $sourceId !== '') {
                    $this->mapId($entityType, $sourceId, $sourceId, 'imported');
                }
            } else {
                ++$stats['skipped'];
            }
        }

        return $stats;
    }

    private function countRows($table)
    {
        $result = DB_query('SELECT COUNT(*) AS total FROM ' . $table, 1);
        if ($result === false) {
            return 0;
        }

        $row = DB_fetchArray($result);

        return isset($row['total']) ? (int) $row['total'] : 0;
    }
}
