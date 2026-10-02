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

function MIGRATOR_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
