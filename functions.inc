<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

global $_CONF;

$pluginPath = $_CONF['path'] . 'plugins/migrator/';

$langFile = $pluginPath . 'language/' . $_CONF['language'] . '.php';
if (file_exists($langFile)) {
    require_once $langFile;
} else {
    require_once $pluginPath . 'language/english.php';
}

require_once $pluginPath . 'migrator.php';

function plugin_getadminoption_migrator()
{
    global $LANG_MIGRATOR;

    if (!SEC_hasRights('migrator.admin')) {
        return array();
    }

    return array(
        $LANG_MIGRATOR['plugin_name'],
        MIGRATOR_adminUrl(),
        0,
        'Migrator Admin'
    );
}


function plugin_getheadercode_migrator()
{
    global $_CONF, $_TABLES;

    $script = isset($_SERVER['PHP_SELF']) ? str_replace('\\', '/', $_SERVER['PHP_SELF']) : '';
    if (strpos($script, '/admin/plugins/migrator/') === false) {
        return '';
    }

    $version = '1.0.0';
    if (isset($_TABLES['plugins'])) {
        $dbVersion = DB_getItem($_TABLES['plugins'], 'pi_version', "pi_name = 'migrator'");
        if (!empty($dbVersion)) {
            $version = (string) $dbVersion;
        }
    }

    $cssPath = rtrim($_CONF['path_html'], "/\\")
        . DIRECTORY_SEPARATOR . 'admin'
        . DIRECTORY_SEPARATOR . 'plugins'
        . DIRECTORY_SEPARATOR . 'migrator'
        . DIRECTORY_SEPARATOR . 'css'
        . DIRECTORY_SEPARATOR . 'admin.css';

    $assetVersion = $version;
    if (is_file($cssPath)) {
        $mtime = @filemtime($cssPath);
        if ($mtime !== false) {
            $assetVersion .= '-' . (int) $mtime;
        }
    }

    $url = rtrim($_CONF['site_admin_url'], '/')
        . '/plugins/migrator/css/admin.css?v='
        . rawurlencode($assetVersion);

    return '<link rel="stylesheet" href="'
        . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
        . '" media="all" type="text/css">' . LB;
}

function plugin_geticon_migrator()
{
    return '';
}

function MIGRATOR_dropAllStagingTables()
{
    global $_DB_table_prefix;

    $prefix = DB_escapeString($_DB_table_prefix . 'migrator_src_');
    $result = DB_query("SHOW TABLES LIKE '{$prefix}%'");

    while ($row = DB_fetchArray($result)) {
        $table = reset($row);
        if (strpos($table, $_DB_table_prefix . 'migrator_src_') === 0) {
            DB_query('DROP TABLE IF EXISTS ' . $table);
        }
    }
}

function plugin_autouninstall_migrator()
{
    MIGRATOR_dropAllStagingTables();

    return array(
        'tables' => array(
            'migrator_jobs',
            'migrator_id_map',
            'migrator_log'
        ),
        'groups' => array('Migrator Admin'),
        'features' => array('migrator.admin')
    );
}
