<?php
// +--------------------------------------------------------------------------+
// | Migrator Plugin - Geeklog                                                |
// +--------------------------------------------------------------------------+
// | MigrationAnalyzer.php                                                    |
// |                                                                          |
// | Source-content analysis and migration planning helpers.                  |
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

class MigratorMigrationAnalyzer
{
    public static function analyse($cms, array $tableMap)
    {
        $definitions = self::definitions($cms);
        $entities = array();

        foreach ($definitions as $entity => $definition) {
            $match = self::findTable($tableMap, $definition['suffixes']);
            $count = 0;

            if ($match !== '') {
                $sql = 'SELECT COUNT(*) AS total FROM ' . $match;
                if (!empty($definition['where'])) {
                    $sql .= ' WHERE ' . $definition['where'];
                }

                $result = DB_query($sql, 1);
                if ($result !== false) {
                    $row = DB_fetchArray($result);
                    $count = isset($row['total']) ? (int) $row['total'] : 0;
                }
            }

            $requiredPlugin = isset($definition['plugin']) ? $definition['plugin'] : '';
            $pluginActive = $requiredPlugin === '' ? true : MIGRATOR_isPluginActive($requiredPlugin);

            $entities[$entity] = array(
                'label' => $definition['label'],
                'table' => $match,
                'count' => $count,
                'status' => $definition['status'],
                'required_plugin' => $requiredPlugin,
                'plugin_active' => $pluginActive
            );
        }

        return $entities;
    }

    private static function definitions($cms)
    {
        if ($cms === 'wordpress') {
            return array(
                'users' => array(
                    'label' => 'Users',
                    'suffixes' => array('users'),
                    'status' => 'supported'
                ),
                'posts' => array(
                    'label' => 'Posts',
                    'suffixes' => array('posts'),
                    'status' => 'supported',
                    'where' => "post_type = 'post' AND post_status IN ('publish','draft','private','pending')"
                ),
                'pages' => array(
                    'label' => 'Pages',
                    'suffixes' => array('posts'),
                    'status' => 'supported',
                    'plugin' => 'staticpages',
                    'where' => "post_type = 'page' AND post_status IN ('publish','draft','private','pending')"
                ),
                'comments' => array(
                    'label' => 'Approved comments',
                    'suffixes' => array('comments'),
                    'status' => 'supported',
                    'where' => "comment_approved = '1'"
                ),
                'terms' => array(
                    'label' => 'Categories',
                    'suffixes' => array('term_taxonomy'),
                    'status' => 'supported',
                    'where' => "taxonomy = 'category'"
                ),
                'media' => array(
                    'label' => 'Media attachments',
                    'suffixes' => array('posts'),
                    'status' => 'planned',
                    'where' => "post_type = 'attachment'"
                )
            );
        }

        $definitions = array(
            'users' => array('label' => 'Users', 'suffixes' => array('users'), 'status' => 'supported'),
            'topics' => array('label' => 'Topics', 'suffixes' => array('topics'), 'status' => 'supported'),
            'stories' => array('label' => 'Stories', 'suffixes' => array('stories'), 'status' => 'supported'),
            'comments' => array('label' => 'Comments', 'suffixes' => array('comments'), 'status' => 'supported'),
            'staticpages' => array(
                'label' => 'Static Pages',
                'suffixes' => array('staticpage'),
                'status' => 'supported',
                'plugin' => 'staticpages'
            ),
            'links' => array('label' => 'Links', 'suffixes' => array('links'), 'status' => 'planned'),
            'polls' => array('label' => 'Polls', 'suffixes' => array('pollquestions', 'pollquestions'), 'status' => 'planned'),
            'calendar' => array('label' => 'Calendar events', 'suffixes' => array('events'), 'status' => 'planned')
        );

        if ($cms === 'glfusion') {
            $definitions['forum'] = array(
                'label' => 'Forum topics',
                'suffixes' => array('ff_topic'),
                'status' => 'supported',
                'plugin' => 'forum'
            );
            $definitions['forum_attachments'] = array(
                'label' => 'Forum attachments → MediaGallery',
                'suffixes' => array('ff_attachments'),
                'status' => 'supported',
                'plugin' => 'mediagallery'
            );
            $definitions['mediagallery_albums'] = array(
                'label' => 'MediaGallery albums',
                'suffixes' => array('mg_albums'),
                'status' => 'supported',
                'plugin' => 'mediagallery'
            );
            $definitions['mediagallery_media'] = array(
                'label' => 'MediaGallery media',
                'suffixes' => array('mg_media'),
                'status' => 'supported',
                'plugin' => 'mediagallery'
            );
        }

        return $definitions;
    }

    private static function findTable(array $tableMap, array $suffixes)
    {
        foreach ($suffixes as $suffix) {
            foreach ($tableMap as $source => $target) {
                $sourceLower = strtolower($source);
                $suffixLower = strtolower($suffix);

                if ($sourceLower === $suffixLower
                    || substr($sourceLower, -strlen($suffixLower)) === $suffixLower
                ) {
                    return $target;
                }
            }
        }

        return '';
    }
}
