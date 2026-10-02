<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

function plugin_autoinstall_migrator($pi_name)
{
    $pi_name = 'migrator';
    $pi_display_name = 'Migrator';
    $pi_admin = 'Migrator Admin';

    return array(
        'info' => array(
            'pi_name' => $pi_name,
            'pi_display_name' => $pi_display_name,
            'pi_version' => '1.0.0',
            'pi_gl_version' => '2.2.2',
            'pi_homepage' => 'https://github.com/hostellerie/migrator'
        ),
        'groups' => array(
            $pi_admin => 'Users in this group can administer CMS migrations'
        ),
        'features' => array(
            'migrator.admin' => 'Full access to the Migrator plugin'
        ),
        'mappings' => array(
            'migrator.admin' => array($pi_admin)
        ),
        'tables' => array(
            'migrator_jobs',
            'migrator_id_map',
            'migrator_log'
        )
    );
}

function plugin_compatible_with_this_version_migrator($pi_name)
{
    global $_CONF, $_DB_dbms;

    if (!defined('VERSION') || version_compare(VERSION, '2.2.2', '!=')) {
        return false;
    }

    $dbFile = $_CONF['path'] . 'plugins/' . $pi_name . '/sql/' . $_DB_dbms . '_install.php';

    return file_exists($dbFile)
        && function_exists('COM_createHTMLDocument')
        && function_exists('SEC_createToken')
        && function_exists('SEC_checkToken');
}

function plugin_postinstall_migrator($pi_name)
{
    COM_errorLog('Migrator 1.0.0 installation completed.', 1);
    return true;
}
