<?php
// +--------------------------------------------------------------------------+
// | Migrator Plugin - Geeklog                                                |
// +--------------------------------------------------------------------------+
// | autoinstall.php                                                          |
// |                                                                          |
// | This file provides helper functions for the automatic plugin install.    |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2026 by the following authors:                             |
// |                                                                          |
// | ::Ben         hostellerie.org  AT gmail DOT com                          |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | This program is free software; you can redistribute it and/or            |
// | modify it under the terms of the GNU General Public License              |
// | as published by the Free Software Foundation; either version 2           |
// | of the License, or (at your option) any later version.                   |
// |                                                                          |
// | This program is distributed in the hope that it will be useful,          |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
// | GNU General Public License for more details.                             |
// |                                                                          |
// | You should have received a copy of the GNU General Public License        |
// | along with this program; if not, write to the Free Software Foundation,  |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.          |
// |                                                                          |
// +--------------------------------------------------------------------------+

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
