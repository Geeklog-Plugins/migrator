<?php

if (!defined('VERSION')) {
    die('This file can not be used on its own.');
}

global $_TABLES, $_DB_table_prefix;

$_TABLES['migrator_jobs'] = $_DB_table_prefix . 'migrator_jobs';
$_TABLES['migrator_id_map'] = $_DB_table_prefix . 'migrator_id_map';
$_TABLES['migrator_log'] = $_DB_table_prefix . 'migrator_log';

function MIGRATOR_dataDir()
{
    global $_CONF;

    $base = isset($_CONF['path_data']) ? rtrim($_CONF['path_data'], "/\\") : '';
    if ($base === '') {
        return '';
    }

    return $base . DIRECTORY_SEPARATOR . 'migrator' . DIRECTORY_SEPARATOR;
}

function MIGRATOR_ensureDataDir()
{
    $dir = MIGRATOR_dataDir();
    if ($dir === '') {
        return false;
    }

    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return false;
    }

    return is_writable($dir);
}

function MIGRATOR_adminUrl()
{
    global $_CONF;

    return rtrim($_CONF['site_admin_url'], '/') . '/plugins/migrator/index.php';
}


function MIGRATOR_destinationStatus()
{
    global $_TABLES;

    $counts = array(
        'users' => isset($_TABLES['users']) ? (int) DB_count($_TABLES['users']) : -1,
        'stories' => isset($_TABLES['stories']) ? (int) DB_count($_TABLES['stories']) : -1,
        'topics' => isset($_TABLES['topics']) ? (int) DB_count($_TABLES['topics']) : -1,
        'comments' => isset($_TABLES['comments']) ? (int) DB_count($_TABLES['comments']) : -1,
        'staticpages' => isset($_TABLES['staticpage']) ? (int) DB_count($_TABLES['staticpage']) : 0
    );

    $fresh = $counts['users'] >= 0
        && $counts['users'] <= 2
        && $counts['stories'] >= 0
        && $counts['stories'] <= 1
        && $counts['topics'] >= 0
        && $counts['topics'] <= 2
        && $counts['comments'] === 0
        && $counts['staticpages'] === 0;

    return array(
        'fresh' => $fresh,
        'counts' => $counts
    );
}


function MIGRATOR_prepareFreshDestination($sourceCms, array $tableMap)
{
    global $_TABLES;

    $status = MIGRATOR_destinationStatus();
    if (!$status['fresh']) {
        return false;
    }

    if (isset($_TABLES['topic_assignments'])) {
        DB_query("DELETE FROM {$_TABLES['topic_assignments']} WHERE type = 'article'");
    }

    if (isset($_TABLES['comments'])) {
        DB_query("DELETE FROM {$_TABLES['comments']} WHERE type IN ('article', 'staticpages')");
    }

    if (isset($_TABLES['stories'])) {
        DB_query("DELETE FROM {$_TABLES['stories']}");
    }

    if (isset($_TABLES['topics'])) {
        DB_query("DELETE FROM {$_TABLES['topics']}");
    }

    if ($sourceCms === 'glfusion') {
        $hasForum = false;
        foreach ($tableMap as $source => $target) {
            if (substr(strtolower($source), -8) === 'ff_topic') {
                $hasForum = true;
                break;
            }
        }

        if ($hasForum && MIGRATOR_isPluginActive('forum')) {
            foreach (array(
                'forum_log',
                'forum_moderators',
                'forum_userprefs',
                'forum_banned_ip',
                'forum_userinfo',
                'forum_topic',
                'forum_forums',
                'forum_categories'
            ) as $tableKey) {
                if (isset($_TABLES[$tableKey])) {
                    DB_query("DELETE FROM {$_TABLES[$tableKey]}");
                }
            }
        }

        $hasMediaGallery = false;
        foreach ($tableMap as $source => $target) {
            if (substr(strtolower($source), -9) === 'mg_albums') {
                $hasMediaGallery = true;
                break;
            }
        }

        if ($hasMediaGallery && MIGRATOR_isPluginActive('mediagallery')) {
            foreach (array(
                'mg_media_albums',
                'mg_media_album_queue',
                'mg_playback_options',
                'mg_usage_tracking',
                'mg_userprefs',
                'mg_sessions',
                'mg_session_items',
                'mg_session_log',
                'mg_sort',
                'mg_rating',
                'mg_mediaqueue',
                'mg_media',
                'mg_albums',
                'mg_category'
            ) as $tableKey) {
                if (isset($_TABLES[$tableKey])) {
                    DB_query("DELETE FROM {$_TABLES[$tableKey]}");
                }
            }
        }
    }

    return true;
}



function MIGRATOR_isPluginActive($pluginName)
{
    global $_TABLES;

    if (!isset($_TABLES['plugins'])) {
        return false;
    }

    $plugin = DB_escapeString((string) $pluginName);
    $result = DB_query(
        "SELECT pi_enabled FROM {$_TABLES['plugins']}
         WHERE pi_name = '{$plugin}' LIMIT 1",
        1
    );

    if ($result === false || DB_numRows($result) === 0) {
        return false;
    }

    $row = DB_fetchArray($result);

    return isset($row['pi_enabled']) && (int) $row['pi_enabled'] === 1;
}

function MIGRATOR_hasCompletedMigration()
{
    global $_TABLES;

    if (!isset($_TABLES['migrator_jobs'])) {
        return false;
    }

    return (int) DB_count($_TABLES['migrator_jobs'], 'status', 'migrated') > 0;
}

function MIGRATOR_resetTestInstallation()
{
    global $_TABLES;

    if (!MIGRATOR_hasCompletedMigration()) {
        return false;
    }

    if (isset($_TABLES['topic_assignments'])) {
        DB_query("DELETE FROM {$_TABLES['topic_assignments']} WHERE type IN ('article', 'staticpages')");
    }

    if (isset($_TABLES['comments'])) {
        DB_query("DELETE FROM {$_TABLES['comments']} WHERE type IN ('article', 'staticpages')");
    }

    if (isset($_TABLES['stories'])) {
        DB_query("DELETE FROM {$_TABLES['stories']}");
    }

    if (MIGRATOR_isPluginActive('staticpages') && isset($_TABLES['staticpage'])) {
        DB_query("DELETE FROM {$_TABLES['staticpage']}");
    }

    if (isset($_TABLES['topics'])) {
        DB_query("DELETE FROM {$_TABLES['topics']}");
    }

    foreach (array(
        'commentnotifications' => 'uid',
        'commentedits' => 'uid',
        'group_assignments' => 'ug_uid',
        'likes' => 'uid',
        'sessions' => 'uid',
        'tokens' => 'owner_id',
        'user_attributes' => 'uid',
        'userautologin' => 'uid',
        'backup_codes' => 'uid'
    ) as $tableKey => $uidField) {
        if (isset($_TABLES[$tableKey])) {
            DB_query("DELETE FROM {$_TABLES[$tableKey]} WHERE {$uidField} > 2");
        }
    }

    if (isset($_TABLES['users'])) {
        DB_query("DELETE FROM {$_TABLES['users']} WHERE uid > 2");
    }

    if (MIGRATOR_isPluginActive('forum')) {
        foreach (array(
            'forum_log',
            'forum_moderators',
            'forum_userprefs',
            'forum_banned_ip',
            'forum_userinfo',
            'forum_topic',
            'forum_forums',
            'forum_categories'
        ) as $tableKey) {
            if (isset($_TABLES[$tableKey])) {
                DB_query("DELETE FROM {$_TABLES[$tableKey]}");
            }
        }
    }

    if (MIGRATOR_isPluginActive('mediagallery')) {
        foreach (array(
            'mg_media_albums',
            'mg_media_album_queue',
            'mg_playback_options',
            'mg_usage_tracking',
            'mg_userprefs',
            'mg_sessions',
            'mg_session_items',
            'mg_session_log',
            'mg_sort',
            'mg_rating',
            'mg_mediaqueue',
            'mg_media',
            'mg_albums',
            'mg_category',
            'mg_watermarks',
            'mg_exif_tags'
        ) as $tableKey) {
            if (isset($_TABLES[$tableKey])) {
                DB_query("DELETE FROM {$_TABLES[$tableKey]}");
            }
        }
    }

    return true;
}

function MIGRATOR_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
