<?php
// +--------------------------------------------------------------------------+
// | Migrator Plugin - Geeklog                                                |
// +--------------------------------------------------------------------------+
// | SourceDetector.php                                                       |
// |                                                                          |
// | Legacy CMS source detection helpers.                                     |
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

class MigratorSourceDetector
{
    public static function detect(array $tables, $sqlSample = '')
    {
        $names = array_map('strtolower', $tables);
        $sample = strtolower((string) $sqlSample);

        if ((self::hasSuffix($names, 'posts') && self::hasSuffix($names, 'options') && self::hasSuffix($names, 'users'))
            || self::hasSuffix($names, 'wp_posts')
            || self::hasSuffix($names, 'wp_options')
        ) {
            return array(
                'cms' => 'wordpress',
                'version' => self::detectWordPressVersion($sample)
            );
        }

        if (strpos($sample, 'glfusion') !== false
            || self::hasSuffix($names, 'mg_albums')
            || self::hasSuffix($names, 'mg_media_albums')
        ) {
            return array('cms' => 'glfusion', 'version' => '');
        }

        if (self::hasSuffix($names, 'stories')
            && self::hasSuffix($names, 'users')
            && self::hasSuffix($names, 'plugins')
        ) {
            return array('cms' => 'legacy_geeklog', 'version' => '');
        }

        return array('cms' => 'unknown', 'version' => '');
    }

    private static function hasSuffix(array $tables, $suffix)
    {
        foreach ($tables as $table) {
            if ($table === $suffix || substr($table, -strlen($suffix)) === $suffix) {
                return true;
            }
        }

        return false;
    }

    private static function detectWordPressVersion($sample)
    {
        if (preg_match("/'db_version'\s*,\s*'([^']+)'/i", $sample, $match)) {
            return 'db ' . $match[1];
        }

        return '';
    }
}
