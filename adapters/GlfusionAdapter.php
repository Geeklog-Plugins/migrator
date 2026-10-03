<?php

require_once __DIR__ . '/LegacyGeeklogAdapter.php';

/**
 * glFusion core content is close enough to Geeklog to share the core adapter.
 * Forum and MediaGallery are handled here with explicit table mappings.
 */
class MigratorGlfusionAdapter extends MigratorLegacyGeeklogAdapter
{
    private $forumAttachmentManifest = array();
    public function run(array $entities)
    {
        global $_TABLES;

        $result = parent::run($entities);

        $result['plugins'] = array();
        $result['media_manifest'] = array();

        if (MIGRATOR_isPluginActive('forum')) {
            $result['plugins']['forum'] = $this->migrateForum();
        } else {
            $result['plugins']['forum'] = array(
                'available' => false,
                'warnings' => array(
                    'glFusion Forum data detected, but the Geeklog Forum plugin is not installed and active.'
                ),
                'tables' => array()
            );
        }

        if (MIGRATOR_isPluginActive('mediagallery')) {
            $result['plugins']['mediagallery'] = $this->migrateMediaGallery();
            $result['plugins']['forum_attachments'] = $this->migrateForumAttachmentsToMediaGallery();
        } else {
            $result['plugins']['mediagallery'] = array(
                'available' => false,
                'warnings' => array(
                    'glFusion MediaGallery data detected, but the Geeklog MediaGallery plugin is not installed and active.'
                ),
                'tables' => array()
            );
            $result['plugins']['forum_attachments'] = array(
                'available' => false,
                'warnings' => array(
                    'glFusion Forum attachments require the Geeklog MediaGallery plugin to be installed and active.'
                ),
                'tables' => array()
            );
        }

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

        if (!empty($result['plugins']['forum_attachments']['warnings'])) {
            foreach ($result['plugins']['forum_attachments']['warnings'] as $warning) {
                $result['warnings'][] = $warning;
            }
        }

        if (!empty($this->forumAttachmentManifest)) {
            foreach ($this->forumAttachmentManifest as $manifestItem) {
                $result['media_manifest'][] = $manifestItem;
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
                'source' => 'public_html/forum/media/',
                'target' => 'public_html/mediagallery/mediaobjects/orig/<first-character>/',
                'note' => 'Copy each glFusion Forum attachment to the exact MediaGallery target filename listed in the migration log/report.'
            );

            if ($this->sourceTable('filemgmt_filedetail') !== '') {
                $result['media_manifest'][] = array(
                    'type' => 'Forum attachments via FileMgmt',
                    'source' => 'public_html/filemgmt_data/files/',
                    'target' => 'public_html/mediagallery/mediaobjects/orig/<first-character>/',
                    'note' => 'Attachments with repository_id > 0 are resolved through the staged FileMgmt tables and converted into the same MediaGallery album.'
                );
            }
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

        foreach (array('ff_bookmarks', 'ff_rating_assoc') as $unsupported) {
            $source = $this->sourceTable($unsupported);
            if ($source !== '') {
                $count = $this->countRows($source);
                $report['warnings'][] = $unsupported . ': ' . $count
                    . ' source records detected but no direct Geeklog Forum destination table exists.';
            }
        }

        return $report;
    }


    private function migrateForumAttachmentsToMediaGallery()
    {
        global $_TABLES;

        $source = $this->sourceTable('ff_attachments');
        if ($source === '') {
            return array(
                'available' => false,
                'warnings' => array(),
                'tables' => array()
            );
        }

        if (!isset($_TABLES['mg_albums'], $_TABLES['mg_media'], $_TABLES['mg_media_albums'])) {
            return array(
                'available' => false,
                'warnings' => array(
                    'MediaGallery destination tables required for Forum attachment conversion are unavailable.'
                ),
                'tables' => array()
            );
        }

        $rows = $this->fetchRows($source);
        $stats = $this->stats();
        $warnings = array();

        if (empty($rows)) {
            return array(
                'available' => true,
                'warnings' => array(),
                'tables' => array('ff_attachments' => $stats)
            );
        }

        $albumId = $this->forumAttachmentAlbumId();
        if (!$this->dryRun) {
            $this->ensureForumAttachmentAlbum($albumId);
        }

        foreach ($rows as $row) {
            $attachmentId = isset($row['id']) ? (int) $row['id'] : 0;
            $topicId = isset($row['topic_id']) ? (int) $row['topic_id'] : 0;
            $repositoryId = isset($row['repository_id']) ? (int) $row['repository_id'] : 0;

            if ($attachmentId <= 0 || $topicId <= 0) {
                ++$stats['skipped'];
                continue;
            }

            $resolved = $this->resolveForumAttachment($row);
            if (!$resolved['ok']) {
                ++$stats['skipped'];
                $warnings[] = 'Forum attachment #' . $attachmentId . ': ' . $resolved['warning'];
                continue;
            }

            $mediaId = sha1(
                'glfusion-forum-attachment:'
                . $attachmentId . ':'
                . $topicId . ':'
                . $resolved['stored_name']
            );
            $mediaFilename = $mediaId;
            $extension = $this->safeExtension($resolved['original_name']);
            if ($extension === '') {
                $extension = $this->safeExtension($resolved['stored_name']);
            }
            if ($extension === '') {
                $extension = 'bin';
            }

            $mimeType = $this->mimeTypeForExtension($extension);
            // Forum attachments are preserved as downloadable generic files.
            // Physical files are copied only after the database migration, so
            // image/video derivatives do not exist yet.
            $mediaType = 4;
            $post = $this->sourceForumPost($topicId);
            $uid = is_array($post) && isset($post['uid']) ? (int) $post['uid'] : 2;
            if ($uid <= 0) {
                $uid = 2;
            }
            $timestamp = is_array($post) && isset($post['date']) && ctype_digit((string) $post['date'])
                ? (int) $post['date']
                : time();

            $description = 'Imported from glFusion Forum attachment #'
                . $attachmentId . ' attached to Forum post #' . $topicId . '.';
            if ($repositoryId > 0) {
                $description .= ' Source FileMgmt repository ID: ' . $repositoryId . '.';
            }
            if ($resolved['description'] !== '') {
                $description .= ' ' . $resolved['description'];
            }

            if ($this->dryRun) {
                $targetRelative = 'mediagallery/mediaobjects/orig/'
                    . $mediaFilename[0] . '/'
                    . $mediaFilename . '.' . $extension;

                $this->forumAttachmentManifest[] = array(
                    'type' => $repositoryId > 0
                        ? 'Forum attachment via FileMgmt'
                        : 'Forum attachment',
                    'source' => 'public_html/' . $resolved['source_relative'],
                    'target' => 'public_html/' . $targetRelative,
                    'note' => 'Original name: ' . $resolved['original_name']
                        . '; Forum post #' . $topicId
                        . '; MediaGallery ID: ' . $mediaId
                );

                ++$stats['would_import'];
                continue;
            }

            if (DB_count($_TABLES['mg_media'], 'media_id', DB_escapeString($mediaId)) > 0) {
                ++$stats['preserved'];
                $this->appendAttachmentLinkToForumPost(
                    $topicId,
                    $mediaId,
                    $resolved['original_name']
                );
                continue;
            }

            $mediaRow = array(
                'media_id' => $mediaId,
                'media_filename' => $mediaFilename,
                'media_original_filename' => $resolved['original_name'],
                'media_mime_ext' => $extension,
                'media_exif' => 0,
                'mime_type' => $mimeType,
                'media_title' => $resolved['original_name'],
                'media_desc' => $description,
                'media_keywords' => 'glfusion,forum,attachment',
                'media_time' => $timestamp,
                'media_views' => 0,
                'media_comments' => 0,
                'media_votes' => 0,
                'media_rating' => '0.00',
                'media_resolution_x' => 0,
                'media_resolution_y' => 0,
                'remote_media' => 0,
                'remote_url' => '',
                'media_tn_attached' => 0,
                'media_tn_image' => '',
                'include_ss' => 1,
                'media_user_id' => $uid,
                'media_user_ip' => '',
                'media_approval' => 0,
                'media_type' => $mediaType,
                'media_upload_time' => $timestamp,
                'media_category' => 0,
                'media_watermarked' => 0,
                'artist' => '',
                'album' => '',
                'genre' => '',
                'v100' => 0,
                'maint' => 0
            );

            $copy = $this->copyIntersectionRow(
                '',
                $_TABLES['mg_media'],
                $mediaRow,
                array('media_id', 'media_filename'),
                false
            );

            if (!$copy['ok']) {
                ++$stats['skipped'];
                $warnings[] = 'Forum attachment #' . $attachmentId
                    . ': failed to create MediaGallery media record.';
                continue;
            }

            $order = $this->nextMediaOrder($albumId);
            DB_query(
                "INSERT INTO {$_TABLES['mg_media_albums']}
                 (album_id, media_id, media_order)
                 VALUES (" . (int) $albumId . ", '"
                 . DB_escapeString($mediaId) . "', " . (int) $order . ")"
            );

            DB_query(
                "UPDATE {$_TABLES['mg_albums']}
                 SET media_count = media_count + 1,
                     last_update = " . (int) time() . "
                 WHERE album_id = " . (int) $albumId
            );

            $this->mapId(
                'glfusion_forum_attachment',
                $attachmentId,
                $mediaId,
                'imported'
            );

            $targetRelative = 'mediagallery/mediaobjects/orig/'
                . $mediaFilename[0] . '/'
                . $mediaFilename . '.' . $extension;

            $this->forumAttachmentManifest[] = array(
                'type' => $repositoryId > 0
                    ? 'Forum attachment via FileMgmt'
                    : 'Forum attachment',
                'source' => 'public_html/' . $resolved['source_relative'],
                'target' => 'public_html/' . $targetRelative,
                'note' => 'Original name: ' . $resolved['original_name']
                    . '; Forum post #' . $topicId
                    . '; MediaGallery ID: ' . $mediaId
            );

            $this->log(
                'info',
                'glfusion_forum_attachment',
                $attachmentId,
                'Copy ' . $resolved['source_relative']
                . ' to ' . $targetRelative
                . ' (original name: ' . $resolved['original_name'] . ')'
            );

            $this->appendAttachmentLinkToForumPost(
                $topicId,
                $mediaId,
                $resolved['original_name']
            );

            ++$stats['imported'];
        }

        return array(
            'available' => true,
            'warnings' => array_values(array_unique($warnings)),
            'tables' => array('ff_attachments' => $stats),
            'album_id' => $albumId,
            'album_title' => 'Forum attachments (glFusion)'
        );
    }

    private function forumAttachmentAlbumId()
    {
        global $_TABLES;

        $max = 0;

        if (isset($_TABLES['mg_albums'])) {
            $value = DB_getItem($_TABLES['mg_albums'], 'MAX(album_id)');
            if ($value !== '' && $value !== null) {
                $max = max($max, (int) $value);
            }
        }

        $sourceAlbums = $this->sourceTable('mg_albums');
        if ($sourceAlbums !== '') {
            $result = DB_query('SELECT MAX(album_id) AS max_id FROM ' . $sourceAlbums, 1);
            if ($result !== false) {
                $row = DB_fetchArray($result);
                if (is_array($row) && isset($row['max_id'])) {
                    $max = max($max, (int) $row['max_id']);
                }
            }
        }

        return $max + 1;
    }

    private function ensureForumAttachmentAlbum($albumId)
    {
        global $_TABLES;

        if (DB_count($_TABLES['mg_albums'], 'album_id', (int) $albumId) > 0) {
            return;
        }

        $row = array(
            'album_id' => (int) $albumId,
            'album_title' => 'Forum attachments (glFusion)',
            'album_desc' => 'Attachments recovered from the glFusion Forum during migration.',
            'album_parent' => 0,
            'album_order' => 9990,
            'skin' => 'default',
            'hidden' => 0,
            'podcast' => 0,
            'album_cover' => '-1',
            'album_cover_filename' => '',
            'media_count' => 0,
            'album_disk_usage' => 0,
            'last_update' => time(),
            'album_views' => 0,
            'enable_comments' => 0,
            'enable_rating' => 0,
            'enable_slideshow' => 0,
            'enable_random' => 0,
            'enable_views' => 1,
            'enable_keywords' => 1,
            'display_album_desc' => 1,
            'enable_sort' => 1,
            'enable_rss' => 0,
            'albums_first' => 1,
            'allow_download' => 1,
            'valid_formats' => 1048575,
            'owner_id' => 2,
            'group_id' => 1,
            'mod_group_id' => 1,
            'perm_owner' => 3,
            'perm_group' => 2,
            'perm_members' => 2,
            'perm_anon' => 2
        );

        $this->copyIntersectionRow(
            '',
            $_TABLES['mg_albums'],
            $row,
            array('album_id'),
            false
        );
    }

    private function resolveForumAttachment(array $row)
    {
        $repositoryId = isset($row['repository_id']) ? (int) $row['repository_id'] : 0;

        if ($repositoryId > 0) {
            return $this->resolveFileMgmtAttachment($repositoryId);
        }

        $raw = isset($row['filename']) ? (string) $row['filename'] : '';
        if ($raw === '') {
            return array(
                'ok' => false,
                'warning' => 'filename is empty'
            );
        }

        $parts = explode(':', $raw, 2);
        $stored = trim($parts[0]);
        $original = isset($parts[1]) && trim($parts[1]) !== ''
            ? trim($parts[1])
            : $stored;

        if ($stored === '' || basename($stored) !== $stored) {
            return array(
                'ok' => false,
                'warning' => 'unsafe or invalid stored filename'
            );
        }

        return array(
            'ok' => true,
            'stored_name' => $stored,
            'original_name' => basename($original),
            'description' => '',
            'source_relative' => 'forum/media/' . $stored
        );
    }

    private function resolveFileMgmtAttachment($repositoryId)
    {
        $detailTable = $this->sourceTable('filemgmt_filedetail');
        if ($detailTable === '') {
            return array(
                'ok' => false,
                'warning' => 'repository_id ' . (int) $repositoryId
                    . ' requires source FileMgmt table filemgmt_filedetail'
            );
        }

        $row = $this->fetchOneBy($detailTable, 'lid', (int) $repositoryId);
        if (!is_array($row)) {
            return array(
                'ok' => false,
                'warning' => 'FileMgmt record #' . (int) $repositoryId . ' was not found'
            );
        }

        $stored = isset($row['url']) ? trim((string) $row['url']) : '';
        $original = isset($row['title']) && trim((string) $row['title']) !== ''
            ? trim((string) $row['title'])
            : $stored;

        if ($stored === '' || basename($stored) !== $stored) {
            return array(
                'ok' => false,
                'warning' => 'FileMgmt record #' . (int) $repositoryId
                    . ' has an unsafe or empty file URL'
            );
        }

        $description = '';
        $descTable = $this->sourceTable('filemgmt_filedesc');
        if ($descTable !== '') {
            $desc = $this->fetchOneBy($descTable, 'lid', (int) $repositoryId);
            if (is_array($desc) && isset($desc['description'])) {
                $description = trim((string) $desc['description']);
            }
        }

        return array(
            'ok' => true,
            'stored_name' => $stored,
            'original_name' => basename($original),
            'description' => $description,
            'source_relative' => 'filemgmt_data/files/' . $stored
        );
    }

    private function sourceForumPost($topicId)
    {
        $source = $this->sourceTable('ff_topic');
        if ($source === '') {
            return null;
        }

        return $this->fetchOneBy($source, 'id', (int) $topicId);
    }

    private function appendAttachmentLinkToForumPost($topicId, $mediaId, $originalName)
    {
        global $_TABLES;

        if (!isset($_TABLES['forum_topic'])
            || DB_count($_TABLES['forum_topic'], 'id', (int) $topicId) === 0
        ) {
            return;
        }

        $tag = '[download:' . $mediaId . ' ' . $originalName . ']';
        $tagEsc = DB_escapeString($tag);
        $result = DB_query(
            "SELECT comment FROM {$_TABLES['forum_topic']}
             WHERE id = " . (int) $topicId . " LIMIT 1",
            1
        );

        if ($result === false) {
            return;
        }

        $row = DB_fetchArray($result);
        if (!is_array($row)) {
            return;
        }

        $comment = isset($row['comment']) ? (string) $row['comment'] : '';
        if (strpos($comment, $tag) !== false) {
            return;
        }

        $separator = $comment === '' ? '' : "\n\n";
        $updated = DB_escapeString($comment . $separator . $tag);
        DB_query(
            "UPDATE {$_TABLES['forum_topic']}
             SET comment = '{$updated}'
             WHERE id = " . (int) $topicId
        );
    }

    private function nextMediaOrder($albumId)
    {
        global $_TABLES;

        $result = DB_query(
            "SELECT MAX(media_order) AS max_order
             FROM {$_TABLES['mg_media_albums']}
             WHERE album_id = " . (int) $albumId,
            1
        );

        if ($result === false) {
            return 10;
        }

        $row = DB_fetchArray($result);
        $max = is_array($row) && isset($row['max_order'])
            ? (int) $row['max_order']
            : 0;

        return max(10, $max + 10);
    }

    private function safeExtension($filename)
    {
        $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]+/', '', $ext);

        return substr($ext, 0, 12);
    }

    private function mimeTypeForExtension($extension)
    {
        $map = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'gz' => 'application/gzip',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'htm' => 'text/html',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo'
        );

        return isset($map[$extension])
            ? $map[$extension]
            : 'application/octet-stream';
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
                $report['warnings'][] = 'MediaGallery album field opacity is mapped to wm_opacity during migration.';
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
            if ($sourceSuffix === 'mg_albums'
                && isset($row['opacity'])
                && !isset($row['wm_opacity'])
            ) {
                $row['wm_opacity'] = $row['opacity'];
            }

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
