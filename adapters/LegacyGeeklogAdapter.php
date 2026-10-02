<?php

class MigratorLegacyGeeklogAdapter
{
    private $jobId;
    private $tableMap;
    private $dryRun;

    public function __construct($jobId, array $tableMap, $dryRun = true)
    {
        $this->jobId = (int) $jobId;
        $this->tableMap = $tableMap;
        $this->dryRun = (bool) $dryRun;
    }

    public function run(array $entities)
    {
        global $_TABLES;

        $result = array(
            'dry_run' => $this->dryRun,
            'entities' => array(),
            'warnings' => array()
        );

        foreach ($entities as $entity) {
            if (!in_array($entity, array('users', 'topics', 'stories', 'comments', 'staticpages'), true)) {
                continue;
            }

            if ($entity === 'staticpages' && !isset($_TABLES['staticpage'])) {
                $result['warnings'][] = 'Static Pages is not installed on the destination.';
                continue;
            }

            $method = 'migrate' . ucfirst($entity);
            $result['entities'][$entity] = $this->{$method}();
        }

        return $result;
    }

    private function migrateUsers()
    {
        global $_TABLES;

        $source = $this->sourceTable('users');
        if ($source === '') {
            return $this->emptyResult('Source users table not found.');
        }

        $rows = $this->fetchRows($source);
        $stats = $this->stats();

        foreach ($rows as $row) {
            $uid = isset($row['uid']) ? (int) $row['uid'] : 0;
            if ($uid <= 0) {
                ++$stats['skipped'];
                continue;
            }

            if ($uid <= 2) {
                $this->mapId('user', $uid, $uid, 'preserved-core');
                ++$stats['preserved'];
                continue;
            }

            $existing = DB_count($_TABLES['users'], 'uid', $uid);
            if ($existing > 0) {
                ++$stats['conflicts'];
                $this->log('warning', 'user', $uid, 'Destination UID already exists.');
                continue;
            }

            $copy = $this->copyIntersectionRow($source, $_TABLES['users'], $row, array('uid'));
            if ($copy['ok']) {
                ++$stats[$this->dryRun ? 'would_import' : 'imported'];
                $this->mapId('user', $uid, $uid, $this->dryRun ? 'planned' : 'imported');
                $this->migrateUserAttributes($uid);
            } else {
                ++$stats['skipped'];
            }
        }

        return $stats;
    }

    private function migrateUserAttributes($uid)
    {
        global $_TABLES;

        if (!isset($_TABLES['user_attributes'])) {
            return;
        }

        $legacyTables = array('userprefs', 'userinfo', 'userindex', 'usercomment');
        $merged = array('uid' => (int) $uid);

        foreach ($legacyTables as $legacy) {
            $source = $this->sourceTable($legacy);
            if ($source === '') {
                continue;
            }

            $row = $this->fetchOneBy($source, 'uid', (int) $uid);
            if (is_array($row)) {
                foreach ($row as $key => $value) {
                    if ($key !== 'uid' && !array_key_exists($key, $merged)) {
                        $merged[$key] = $value;
                    }
                }
            }
        }

        if (count($merged) === 1) {
            return;
        }

        if ($this->dryRun || DB_count($_TABLES['user_attributes'], 'uid', (int) $uid) > 0) {
            return;
        }

        $this->copyIntersectionRow('', $_TABLES['user_attributes'], $merged, array('uid'), false);
    }

    private function migrateTopics()
    {
        global $_TABLES;

        $source = $this->sourceTable('topics');
        if ($source === '') {
            return $this->emptyResult('Source topics table not found.');
        }

        $rows = $this->fetchRows($source);
        $stats = $this->stats();

        foreach ($rows as $row) {
            $tid = isset($row['tid']) ? (string) $row['tid'] : '';
            if ($tid === '') {
                ++$stats['skipped'];
                continue;
            }

            if (DB_count($_TABLES['topics'], 'tid', DB_escapeString($tid)) > 0) {
                ++$stats['conflicts'];
                $this->mapId('topic', $tid, $tid, 'existing');
                continue;
            }

            $copy = $this->copyIntersectionRow($source, $_TABLES['topics'], $row, array('tid'));
            if ($copy['ok']) {
                ++$stats[$this->dryRun ? 'would_import' : 'imported'];
                $this->mapId('topic', $tid, $tid, $this->dryRun ? 'planned' : 'imported');
            } else {
                ++$stats['skipped'];
            }
        }

        return $stats;
    }

    private function migrateStories()
    {
        global $_TABLES;

        $source = $this->sourceTable('stories');
        if ($source === '') {
            return $this->emptyResult('Source stories table not found.');
        }

        $rows = $this->fetchRows($source);
        $stats = $this->stats();

        foreach ($rows as $row) {
            $sid = isset($row['sid']) ? (string) $row['sid'] : '';
            if ($sid === '') {
                ++$stats['skipped'];
                continue;
            }

            if (DB_count($_TABLES['stories'], 'sid', DB_escapeString($sid)) > 0) {
                ++$stats['conflicts'];
                $this->mapId('story', $sid, $sid, 'existing');
                continue;
            }

            $copy = $this->copyIntersectionRow($source, $_TABLES['stories'], $row, array('sid'));
            if ($copy['ok']) {
                ++$stats[$this->dryRun ? 'would_import' : 'imported'];
                $this->mapId('story', $sid, $sid, $this->dryRun ? 'planned' : 'imported');
                $this->migrateStoryTopic($sid, $row);
            } else {
                ++$stats['skipped'];
            }
        }

        return $stats;
    }

    private function migrateStoryTopic($sid, array $row)
    {
        global $_TABLES;

        if (!isset($_TABLES['topic_assignments'])) {
            return;
        }

        $tid = '';
        if (isset($row['tid'])) {
            $tid = (string) $row['tid'];
        } elseif (isset($row['topic'])) {
            $tid = (string) $row['topic'];
        }

        if ($tid === '') {
            return;
        }

        if ($this->dryRun) {
            return;
        }

        $tidEsc = DB_escapeString($tid);
        $sidEsc = DB_escapeString($sid);

        if (DB_count($_TABLES['topics'], 'tid', $tidEsc) === 0) {
            $this->log('warning', 'story', $sid, 'Topic assignment skipped because topic does not exist: ' . $tid);
            return;
        }

        $sql = "SELECT COUNT(*) AS total FROM {$_TABLES['topic_assignments']}
            WHERE tid = '{$tidEsc}' AND type = 'article' AND id = '{$sidEsc}'";
        $check = DB_query($sql);
        $existing = DB_fetchArray($check);

        if ((int) $existing['total'] === 0) {
            DB_query("INSERT INTO {$_TABLES['topic_assignments']}
                (tid, type, subtype, id, inherit, tdefault)
                VALUES ('{$tidEsc}', 'article', '', '{$sidEsc}', 1, 1)");
        }
    }

    private function migrateComments()
    {
        global $_TABLES;

        $source = $this->sourceTable('comments');
        if ($source === '') {
            return $this->emptyResult('Source comments table not found.');
        }

        $rows = $this->fetchRows($source);
        $stats = $this->stats();

        foreach ($rows as $row) {
            $cid = isset($row['cid']) ? (int) $row['cid'] : 0;
            if ($cid <= 0) {
                ++$stats['skipped'];
                continue;
            }

            if (DB_count($_TABLES['comments'], 'cid', $cid) > 0) {
                ++$stats['conflicts'];
                $this->mapId('comment', $cid, $cid, 'existing');
                continue;
            }

            if (!isset($row['type']) || $row['type'] === '') {
                $row['type'] = 'article';
            }

            $copy = $this->copyIntersectionRow($source, $_TABLES['comments'], $row, array('cid'));
            if ($copy['ok']) {
                ++$stats[$this->dryRun ? 'would_import' : 'imported'];
                $this->mapId('comment', $cid, $cid, $this->dryRun ? 'planned' : 'imported');
            } else {
                ++$stats['skipped'];
            }
        }

        return $stats;
    }

    private function migrateStaticpages()
    {
        global $_TABLES;

        $source = $this->sourceTable('staticpage');
        if ($source === '') {
            return $this->emptyResult('Source static pages table not found.');
        }

        $rows = $this->fetchRows($source);
        $stats = $this->stats();

        foreach ($rows as $row) {
            $id = isset($row['sp_id']) ? (string) $row['sp_id'] : '';
            if ($id === '') {
                ++$stats['skipped'];
                continue;
            }

            if (DB_count($_TABLES['staticpage'], 'sp_id', DB_escapeString($id)) > 0) {
                ++$stats['conflicts'];
                $this->mapId('staticpage', $id, $id, 'existing');
                continue;
            }

            $copy = $this->copyIntersectionRow($source, $_TABLES['staticpage'], $row, array('sp_id'));
            if ($copy['ok']) {
                ++$stats[$this->dryRun ? 'would_import' : 'imported'];
                $this->mapId('staticpage', $id, $id, $this->dryRun ? 'planned' : 'imported');
            } else {
                ++$stats['skipped'];
            }
        }

        return $stats;
    }

    private function copyIntersectionRow($sourceTable, $targetTable, array $row, array $required, $logFailure = true)
    {
        $targetColumns = $this->columns($targetTable);
        if (empty($targetColumns)) {
            return array('ok' => false, 'reason' => 'target-columns');
        }

        $data = array();
        foreach ($row as $column => $value) {
            if (isset($targetColumns[$column])) {
                $data[$column] = $value;
            }
        }

        foreach ($required as $requiredColumn) {
            if (!array_key_exists($requiredColumn, $data)) {
                return array('ok' => false, 'reason' => 'missing-required-column');
            }
        }

        if ($this->dryRun) {
            return array('ok' => true, 'columns' => array_keys($data));
        }

        $columns = array();
        $values = array();

        foreach ($data as $column => $value) {
            $columns[] = $column;

            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $values[] = "'" . DB_escapeString((string) $value) . "'";
            }
        }

        $sql = 'INSERT INTO ' . $targetTable
            . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')';

        $ok = DB_query($sql, 1) !== false;
        if (!$ok && $logFailure) {
            $this->log('error', 'row', '', 'Insert failed for target table ' . $targetTable);
        }

        return array('ok' => $ok, 'columns' => array_keys($data));
    }

    private function columns($table)
    {
        static $cache = array();

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $cache[$table] = array();
        $result = DB_query('SHOW COLUMNS FROM ' . $table, 1);
        if ($result === false) {
            return $cache[$table];
        }

        while ($row = DB_fetchArray($result)) {
            if (isset($row['Field'])) {
                $cache[$table][$row['Field']] = true;
            }
        }

        return $cache[$table];
    }

    private function fetchRows($table)
    {
        $rows = array();
        $result = DB_query('SELECT * FROM ' . $table);

        while ($row = DB_fetchArray($result)) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function fetchOneBy($table, $field, $value)
    {
        $columns = $this->columns($table);
        if (!isset($columns[$field])) {
            return null;
        }

        $value = DB_escapeString((string) $value);
        $result = DB_query("SELECT * FROM {$table} WHERE {$field} = '{$value}' LIMIT 1", 1);

        if ($result === false || DB_numRows($result) === 0) {
            return null;
        }

        return DB_fetchArray($result);
    }

    private function sourceTable($suffix)
    {
        $suffix = strtolower($suffix);

        foreach ($this->tableMap as $source => $target) {
            $sourceLower = strtolower($source);
            if ($sourceLower === $suffix || substr($sourceLower, -strlen($suffix)) === $suffix) {
                return $target;
            }
        }

        return '';
    }

    private function mapId($entityType, $sourceId, $targetId, $status)
    {
        global $_TABLES;

        if ($this->dryRun) {
            return;
        }

        $entity = DB_escapeString($entityType);
        $source = DB_escapeString((string) $sourceId);
        $target = DB_escapeString((string) $targetId);
        $status = DB_escapeString($status);

        DB_query("INSERT INTO {$_TABLES['migrator_id_map']}
            (job_id, entity_type, source_id, target_id, status)
            VALUES ({$this->jobId}, '{$entity}', '{$source}', '{$target}', '{$status}')
            ON DUPLICATE KEY UPDATE target_id = '{$target}', status = '{$status}'");
    }

    private function log($level, $entityType, $sourceId, $message)
    {
        global $_TABLES;

        $level = DB_escapeString($level);
        $entity = DB_escapeString($entityType);
        $source = DB_escapeString((string) $sourceId);
        $message = DB_escapeString($message);
        $now = date('Y-m-d H:i:s');

        DB_query("INSERT INTO {$_TABLES['migrator_log']}
            (job_id, level, entity_type, source_id, message, created)
            VALUES ({$this->jobId}, '{$level}', '{$entity}', '{$source}', '{$message}', '{$now}')");
    }

    private function stats()
    {
        return array(
            'would_import' => 0,
            'imported' => 0,
            'preserved' => 0,
            'conflicts' => 0,
            'skipped' => 0
        );
    }

    private function emptyResult($warning)
    {
        $stats = $this->stats();
        $stats['warning'] = $warning;

        return $stats;
    }
}
