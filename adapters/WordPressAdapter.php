<?php

require_once __DIR__ . '/LegacyGeeklogAdapter.php';

class MigratorWordPressAdapter extends MigratorLegacyGeeklogAdapter
{
    private $userMap = array();
    private $postMap = array();
    private $termMap = array();
    private $taxonomyMap = array();
    private $usedUsernames = array();
    private $usedIds = array();

    public function run(array $entities = array())
    {
        global $_CONF;

        $result = array(
            'dry_run' => $this->dryRun,
            'entities' => array(),
            'warnings' => array(),
            'media_manifest' => array()
        );

        $result['entities']['users'] = $this->migrateUsers();
        $result['entities']['categories'] = $this->migrateCategories();
        $result['entities']['posts'] = $this->migratePostsAndPages();
        $result['entities']['topic_assignments'] = $this->migrateCategoryAssignments();
        $result['entities']['comments'] = $this->migrateComments();

        $media = $this->wordpressMediaBase();
        if ($media['source_url'] !== '') {
            $result['media_manifest'][] = array(
                'type' => 'WordPress uploads',
                'source' => 'wp-content/uploads/',
                'target' => 'public_html/images/wordpress/',
                'note' => 'Copy the WordPress uploads tree here. Migrated post/page content rewrites the detected WordPress uploads base URL to '
                    . rtrim($_CONF['site_url'], '/') . '/images/wordpress/.'
            );
        } else {
            $result['warnings'][] = 'WordPress upload base URL could not be detected from wp_options; media URLs are left unchanged.';
        }

        $result['warnings'][] = 'WordPress password hashes are not copied. Imported users must set a Geeklog password through the normal password-reset flow.';

        return $result;
    }

    private function migrateUsers()
    {
        global $_TABLES;

        $source = $this->sourceTable('users');
        if ($source === '') {
            return $this->emptyResult('Source WordPress users table not found.');
        }

        $rows = $this->queryRows('SELECT * FROM ' . $source . ' ORDER BY ID ASC');
        $stats = $this->stats();

        $maxSource = 2;
        foreach ($rows as $row) {
            $maxSource = max($maxSource, isset($row['ID']) ? (int) $row['ID'] : 0);
        }

        $maxTarget = (int) DB_getItem($_TABLES['users'], 'MAX(uid)');
        $nextUid = max($maxSource, $maxTarget, 2) + 1;

        $reservedSourceIds = array();
        foreach ($rows as $row) {
            $id = isset($row['ID']) ? (int) $row['ID'] : 0;
            if ($id > 2) {
                $reservedSourceIds[$id] = true;
            }
        }

        $existingUsers = $this->queryRows('SELECT uid, username FROM ' . $_TABLES['users']);
        foreach ($existingUsers as $existing) {
            $this->usedIds[(int) $existing['uid']] = true;
            $this->usedUsernames[strtolower((string) $existing['username'])] = true;
        }

        foreach ($rows as $row) {
            $sourceId = isset($row['ID']) ? (int) $row['ID'] : 0;
            if ($sourceId <= 0) {
                ++$stats['skipped'];
                continue;
            }

            $targetId = $sourceId;
            if ($sourceId <= 2 || isset($this->usedIds[$targetId])) {
                while (isset($this->usedIds[$nextUid]) || isset($reservedSourceIds[$nextUid])) {
                    ++$nextUid;
                }
                $targetId = $nextUid++;
            }

            $this->usedIds[$targetId] = true;
            $this->userMap[$sourceId] = $targetId;

            $login = isset($row['user_login']) ? (string) $row['user_login'] : '';
            $username = $this->uniqueUsername($login, $sourceId);
            $email = isset($row['user_email']) ? (string) $row['user_email'] : '';
            $fullname = isset($row['display_name']) ? (string) $row['display_name'] : $username;
            $homepage = isset($row['user_url']) ? (string) $row['user_url'] : '';
            $regdate = isset($row['user_registered']) && $row['user_registered'] !== '0000-00-00 00:00:00'
                ? $row['user_registered']
                : date('Y-m-d H:i:s');

            if ($this->dryRun) {
                ++$stats['would_import'];
                continue;
            }

            $data = array(
                'uid' => $targetId,
                'username' => $username,
                'fullname' => $fullname,
                'passwd' => '',
                'salt' => '',
                'algorithm' => 0,
                'stretch' => 1,
                'email' => $email !== '' ? $email : null,
                'homepage' => $homepage !== '' ? $homepage : null,
                'sig' => '',
                'regdate' => $regdate,
                'cookietimeout' => 28800,
                'status' => 3,
                'postmode' => 'html'
            );

            if ($this->insertAssoc($_TABLES['users'], $data)) {
                ++$stats['imported'];
                $this->mapId('wordpress_user', $sourceId, $targetId, 'imported');
                $this->ensureCoreUserGroups($targetId);
            } else {
                ++$stats['skipped'];
            }
        }

        return $stats;
    }

    private function migrateCategories()
    {
        global $_TABLES;

        $terms = $this->sourceTable('terms');
        $taxonomy = $this->sourceTable('term_taxonomy');

        if ($terms === '' || $taxonomy === '') {
            return $this->emptyResult('WordPress category tables were not found.');
        }

        $sql = "SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.parent
            FROM {$terms} AS t
            INNER JOIN {$taxonomy} AS tt ON tt.term_id = t.term_id
            WHERE tt.taxonomy = 'category'
            ORDER BY tt.term_taxonomy_id ASC";

        $rows = $this->queryRows($sql);
        $stats = $this->stats();
        $usedTopics = array();

        $existing = $this->queryRows('SELECT tid FROM ' . $_TABLES['topics']);
        foreach ($existing as $row) {
            $usedTopics[strtolower((string) $row['tid'])] = true;
        }

        foreach ($rows as $row) {
            $taxonomyId = (int) $row['term_taxonomy_id'];
            $termId = (int) $row['term_id'];
            $tid = $this->uniqueIdentifier(
                isset($row['slug']) ? $row['slug'] : '',
                'wp-category-' . $termId,
                75,
                $usedTopics
            );

            $this->taxonomyMap[$taxonomyId] = $tid;
            $this->termMap[$termId] = $tid;

            if ($this->dryRun) {
                ++$stats['would_import'];
                continue;
            }

            $groupId = (int) DB_getItem($_TABLES['groups'], 'grp_id', "grp_name = 'Topic Admin'");
            if ($groupId <= 0) {
                $groupId = 1;
            }

            $data = array(
                'tid' => $tid,
                'topic' => isset($row['name']) ? $row['name'] : $tid,
                'title' => isset($row['name']) ? $row['name'] : $tid,
                'sortnum' => 0,
                'limitnews' => 10,
                'parent_id' => 'root',
                'inherit' => 1,
                'hidden' => 0,
                'owner_id' => 2,
                'group_id' => $groupId,
                'perm_owner' => 3,
                'perm_group' => 2,
                'perm_members' => 2,
                'perm_anon' => 2
            );

            if ($this->insertAssoc($_TABLES['topics'], $data)) {
                ++$stats['imported'];
                $this->mapId('wordpress_category', $taxonomyId, $tid, 'imported');
            } else {
                ++$stats['skipped'];
            }
        }

        if (!$this->dryRun) {
            foreach ($rows as $row) {
                $parentTerm = isset($row['parent']) ? (int) $row['parent'] : 0;
                $termId = (int) $row['term_id'];
                if ($parentTerm > 0
                    && isset($this->termMap[$termId], $this->termMap[$parentTerm])
                ) {
                    $tidEsc = DB_escapeString($this->termMap[$termId]);
                    $parentEsc = DB_escapeString($this->termMap[$parentTerm]);
                    DB_query("UPDATE {$_TABLES['topics']}
                        SET parent_id = '{$parentEsc}'
                        WHERE tid = '{$tidEsc}'");
                }
            }
        }

        return $stats;
    }

    private function migratePostsAndPages()
    {
        global $_TABLES;

        $source = $this->sourceTable('posts');
        if ($source === '') {
            return $this->emptyResult('Source WordPress posts table not found.');
        }

        $sql = "SELECT * FROM {$source}
            WHERE post_type IN ('post', 'page')
            AND post_status IN ('publish', 'draft', 'private', 'pending')
            ORDER BY ID ASC";

        $rows = $this->queryRows($sql);
        $stats = $this->stats();
        $usedStories = array();
        $usedPages = array();

        foreach ($this->queryRows('SELECT sid FROM ' . $_TABLES['stories']) as $row) {
            $usedStories[strtolower((string) $row['sid'])] = true;
        }
        if (isset($_TABLES['staticpage'])) {
            foreach ($this->queryRows('SELECT sp_id FROM ' . $_TABLES['staticpage']) as $row) {
                $usedPages[strtolower((string) $row['sp_id'])] = true;
            }
        }

        $media = $this->wordpressMediaBase();

        foreach ($rows as $row) {
            $sourceId = (int) $row['ID'];
            $postType = (string) $row['post_type'];
            $slug = isset($row['post_name']) ? $row['post_name'] : '';
            $targetId = $postType === 'page'
                ? $this->uniqueIdentifier($slug, 'wp-page-' . $sourceId, 128, $usedPages)
                : $this->uniqueIdentifier($slug, 'wp-post-' . $sourceId, 128, $usedStories);

            $this->postMap[$sourceId] = array(
                'type' => $postType === 'page' ? 'staticpages' : 'article',
                'id' => $targetId,
                'title' => isset($row['post_title']) ? (string) $row['post_title'] : ''
            );

            $content = isset($row['post_content']) ? (string) $row['post_content'] : '';
            $content = $this->rewriteMediaUrls($content, $media);
            $author = isset($row['post_author']) ? (int) $row['post_author'] : 0;
            $uid = isset($this->userMap[$author]) ? $this->userMap[$author] : 2;
            $draft = ((string) $row['post_status'] === 'publish') ? 0 : 1;
            $commentsOpen = isset($row['comment_status']) && $row['comment_status'] === 'open';

            if ($this->dryRun) {
                ++$stats['would_import'];
                continue;
            }

            if ($postType === 'page') {
                if (!isset($_TABLES['staticpage'])) {
                    ++$stats['skipped'];
                    continue;
                }

                $data = array(
                    'sp_id' => $targetId,
                    'sp_title' => (string) $row['post_title'],
                    'sp_page_title' => (string) $row['post_title'],
                    'sp_content' => $content,
                    'created' => $this->validDate($row['post_date']),
                    'modified' => $this->validDate($row['post_modified']),
                    'sp_format' => 'html',
                    'commentcode' => $commentsOpen ? 0 : 1,
                    'draft_flag' => $draft,
                    'search' => 1,
                    'owner_id' => $uid,
                    'group_id' => 1,
                    'perm_owner' => 3,
                    'perm_group' => 2,
                    'perm_members' => 2,
                    'perm_anon' => 2,
                    'postmode' => 'html'
                );

                if ($this->insertAssoc($_TABLES['staticpage'], $data)) {
                    ++$stats['imported'];
                    $this->mapId('wordpress_page', $sourceId, $targetId, 'imported');
                } else {
                    ++$stats['skipped'];
                }
            } else {
                list($intro, $body) = $this->splitWordPressContent(
                    $content,
                    isset($row['post_excerpt']) ? (string) $row['post_excerpt'] : ''
                );

                $groupId = (int) DB_getItem($_TABLES['groups'], 'grp_id', "grp_name = 'Story Admin'");
                if ($groupId <= 0) {
                    $groupId = 1;
                }

                $data = array(
                    'sid' => $targetId,
                    'uid' => $uid,
                    'draft_flag' => $draft,
                    'date' => $this->validDate($row['post_date']),
                    'modified' => $this->validDate($row['post_modified']),
                    'title' => (string) $row['post_title'],
                    'page_title' => (string) $row['post_title'],
                    'introtext' => $intro,
                    'bodytext' => $body,
                    'structured_data_type' => 'core-article',
                    'commentcode' => $commentsOpen ? 0 : 1,
                    'statuscode' => 0,
                    'postmode' => 'html',
                    'frontpage' => 1,
                    'owner_id' => $uid,
                    'group_id' => $groupId,
                    'perm_owner' => 3,
                    'perm_group' => 2,
                    'perm_members' => 2,
                    'perm_anon' => 2
                );

                if ($this->insertAssoc($_TABLES['stories'], $data)) {
                    ++$stats['imported'];
                    $this->mapId('wordpress_post', $sourceId, $targetId, 'imported');
                } else {
                    ++$stats['skipped'];
                }
            }
        }

        return $stats;
    }

    private function migrateCategoryAssignments()
    {
        global $_TABLES;

        $relationships = $this->sourceTable('term_relationships');
        $taxonomy = $this->sourceTable('term_taxonomy');
        if ($relationships === '' || $taxonomy === '') {
            return $this->emptyResult('WordPress taxonomy relationships were not found.');
        }

        $sql = "SELECT tr.object_id, tr.term_taxonomy_id
            FROM {$relationships} AS tr
            INNER JOIN {$taxonomy} AS tt
                ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tt.taxonomy = 'category'
            ORDER BY tr.object_id ASC, tr.term_order ASC";

        $rows = $this->queryRows($sql);
        $stats = $this->stats();
        $defaultSeen = array();

        foreach ($rows as $row) {
            $postId = (int) $row['object_id'];
            $taxonomyId = (int) $row['term_taxonomy_id'];

            if (!isset($this->postMap[$postId], $this->taxonomyMap[$taxonomyId])) {
                ++$stats['skipped'];
                continue;
            }

            if ($this->postMap[$postId]['type'] !== 'article') {
                ++$stats['skipped'];
                continue;
            }

            if ($this->dryRun) {
                ++$stats['would_import'];
                continue;
            }

            $sid = DB_escapeString($this->postMap[$postId]['id']);
            $tid = DB_escapeString($this->taxonomyMap[$taxonomyId]);
            $tdefault = isset($defaultSeen[$postId]) ? 0 : 1;
            $defaultSeen[$postId] = true;

            $check = DB_query("SELECT COUNT(*) AS total FROM {$_TABLES['topic_assignments']}
                WHERE tid = '{$tid}' AND type = 'article' AND id = '{$sid}'");
            $existing = DB_fetchArray($check);
            if ((int) $existing['total'] > 0) {
                ++$stats['preserved'];
                continue;
            }

            DB_query("INSERT INTO {$_TABLES['topic_assignments']}
                (tid, type, subtype, id, inherit, tdefault)
                VALUES ('{$tid}', 'article', '', '{$sid}', 1, {$tdefault})");
            ++$stats['imported'];
        }

        return $stats;
    }

    private function migrateComments()
    {
        global $_CONF, $_TABLES;

        $source = $this->sourceTable('comments');
        if ($source === '') {
            return $this->emptyResult('Source WordPress comments table not found.');
        }

        $rows = $this->queryRows(
            "SELECT * FROM {$source}
             WHERE comment_approved = '1'
             ORDER BY comment_date ASC, comment_ID ASC"
        );

        $stats = $this->stats();
        $parentMap = array();
        $approvedIds = array();
        $itemsToRebuild = array();

        foreach ($rows as $row) {
            $commentId = (int) $row['comment_ID'];
            $parentMap[$commentId] = isset($row['comment_parent']) ? (int) $row['comment_parent'] : 0;
            $approvedIds[$commentId] = true;
        }

        foreach ($rows as $row) {
            $sourceId = (int) $row['comment_ID'];
            $postId = (int) $row['comment_post_ID'];

            if (!isset($this->postMap[$postId])) {
                ++$stats['skipped'];
                continue;
            }

            $targetItem = $this->postMap[$postId];

            if ($this->dryRun) {
                ++$stats['would_import'];
                continue;
            }

            if (DB_count($_TABLES['comments'], 'cid', $sourceId) > 0) {
                ++$stats['conflicts'];
                continue;
            }

            $wpUser = isset($row['user_id']) ? (int) $row['user_id'] : 0;
            $uid = isset($this->userMap[$wpUser]) ? $this->userMap[$wpUser] : 1;
            $name = ($uid === 1 && !empty($row['comment_author']))
                ? (string) $row['comment_author']
                : null;
            $type = $targetItem['type'];
            $sid = $targetItem['id'];
            $parentId = isset($row['comment_parent']) ? (int) $row['comment_parent'] : 0;
            if ($parentId > 0 && !isset($approvedIds[$parentId])) {
                $parentId = 0;
            }

            $parentMap[$sourceId] = $parentId;
            $depth = $this->commentDepth($sourceId, $parentMap);
            $title = $targetItem['title'] !== '' ? $targetItem['title'] : 'Comment';

            $data = array(
                'cid' => $sourceId,
                'type' => $type,
                'sid' => $sid,
                'date' => $this->validDate($row['comment_date']),
                'title' => substr($title, 0, 128),
                'comment' => (string) $row['comment_content'],
                'pid' => $parentId,
                'lft' => 0,
                'rht' => 0,
                'indent' => $depth,
                'name' => $name,
                'uid' => $uid,
                'seq' => 0
            );

            if ($this->insertAssoc($_TABLES['comments'], $data)) {
                ++$stats['imported'];
                $this->mapId('wordpress_comment', $sourceId, $sourceId, 'imported');
                $itemsToRebuild[$type . ':' . $sid] = array($type, $sid);
            } else {
                ++$stats['skipped'];
            }
        }

        if (!$this->dryRun && !empty($itemsToRebuild)) {
            require_once $_CONF['path_system'] . 'lib-comment.php';

            foreach ($itemsToRebuild as $item) {
                CMT_rebuildTree($item[0], $item[1]);
                $count = DB_count(
                    $_TABLES['comments'],
                    array('type', 'sid'),
                    array($item[0], $item[1])
                );

                if ($item[0] === 'article') {
                    $sidEsc = DB_escapeString($item[1]);
                    DB_query("UPDATE {$_TABLES['stories']}
                        SET comments = " . (int) $count . "
                        WHERE sid = '{$sidEsc}'");
                }
            }
        }

        return $stats;
    }

    private function wordpressMediaBase()
    {
        $options = $this->sourceTable('options');
        if ($options === '') {
            return array('source_url' => '', 'target_url' => '');
        }

        $siteUrl = '';
        $uploadUrl = '';

        $result = DB_query("SELECT option_name, option_value FROM {$options}
            WHERE option_name IN ('siteurl', 'upload_url_path')", 1);

        if ($result !== false) {
            while ($row = DB_fetchArray($result)) {
                if ($row['option_name'] === 'siteurl') {
                    $siteUrl = rtrim((string) $row['option_value'], '/');
                } elseif ($row['option_name'] === 'upload_url_path') {
                    $uploadUrl = rtrim((string) $row['option_value'], '/');
                }
            }
        }

        if ($uploadUrl === '' && $siteUrl !== '') {
            $uploadUrl = $siteUrl . '/wp-content/uploads';
        }

        global $_CONF;

        return array(
            'source_url' => $uploadUrl,
            'target_url' => rtrim($_CONF['site_url'], '/') . '/images/wordpress'
        );
    }

    private function rewriteMediaUrls($content, array $media)
    {
        if ($media['source_url'] === '' || $media['target_url'] === '') {
            return $content;
        }

        return str_replace(
            array($media['source_url'], str_replace('https://', 'http://', $media['source_url'])),
            array($media['target_url'], $media['target_url']),
            $content
        );
    }

    private function splitWordPressContent($content, $excerpt)
    {
        if ($excerpt !== '') {
            return array($excerpt, $content);
        }

        $parts = preg_split('/<!--more(?:.*?)?-->/', $content, 2);
        if (is_array($parts) && count($parts) === 2) {
            return array($parts[0], $parts[1]);
        }

        return array($content, '');
    }

    private function commentDepth($commentId, array $parentMap)
    {
        $depth = 0;
        $seen = array();
        $current = $commentId;

        while (isset($parentMap[$current]) && $parentMap[$current] > 0) {
            if (isset($seen[$current]) || $depth >= 50) {
                break;
            }

            $seen[$current] = true;
            $current = $parentMap[$current];
            ++$depth;
        }

        return $depth;
    }

    private function uniqueUsername($value, $sourceId)
    {
        $base = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $value);
        if ($base === '') {
            $base = 'wpuser' . (int) $sourceId;
        }

        $base = substr($base, 0, 16);
        $candidate = $base;
        $counter = 1;

        while (isset($this->usedUsernames[strtolower($candidate)])) {
            $suffix = (string) $counter++;
            $candidate = substr($base, 0, max(1, 16 - strlen($suffix))) . $suffix;
        }

        $this->usedUsernames[strtolower($candidate)] = true;

        return $candidate;
    }

    private function uniqueIdentifier($value, $fallback, $maxLength, array &$used)
    {
        $base = strtolower((string) $value);
        $base = preg_replace('/[^a-z0-9._-]+/', '-', $base);
        $base = trim($base, '-._');

        if ($base === '') {
            $base = strtolower($fallback);
        }

        $base = substr($base, 0, $maxLength);
        $candidate = $base;
        $counter = 2;

        while (isset($used[strtolower($candidate)])) {
            $suffix = '-' . $counter++;
            $candidate = substr($base, 0, max(1, $maxLength - strlen($suffix))) . $suffix;
        }

        $used[strtolower($candidate)] = true;

        return $candidate;
    }

    private function validDate($value)
    {
        $value = (string) $value;
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return date('Y-m-d H:i:s');
        }

        return $value;
    }

    private function queryRows($sql)
    {
        $rows = array();
        $result = DB_query($sql, 1);
        if ($result === false) {
            return $rows;
        }

        while ($row = DB_fetchArray($result)) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function insertAssoc($table, array $data)
    {
        $columns = $this->columns($table);
        $filtered = array();

        foreach ($data as $column => $value) {
            if (isset($columns[$column])) {
                $filtered[$column] = $value;
            }
        }

        if (empty($filtered)) {
            return false;
        }

        $names = array();
        $values = array();

        foreach ($filtered as $column => $value) {
            $names[] = $column;
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $values[] = "'" . DB_escapeString((string) $value) . "'";
            }
        }

        return DB_query(
            'INSERT INTO ' . $table
            . ' (' . implode(',', $names) . ') VALUES (' . implode(',', $values) . ')',
            1
        ) !== false;
    }
}
