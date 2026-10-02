<?php

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
                $result = DB_query('SELECT COUNT(*) AS total FROM ' . $match, 1);
                if ($result !== false) {
                    $row = DB_fetchArray($result);
                    $count = isset($row['total']) ? (int) $row['total'] : 0;
                }
            }

            $entities[$entity] = array(
                'label' => $definition['label'],
                'table' => $match,
                'count' => $count,
                'status' => $definition['status']
            );
        }

        return $entities;
    }

    private static function definitions($cms)
    {
        if ($cms === 'wordpress') {
            return array(
                'users' => array('label' => 'Users', 'suffixes' => array('users'), 'status' => 'supported'),
                'posts' => array('label' => 'Posts and pages', 'suffixes' => array('posts'), 'status' => 'supported'),
                'comments' => array('label' => 'Comments', 'suffixes' => array('comments'), 'status' => 'supported'),
                'terms' => array('label' => 'Categories and tags', 'suffixes' => array('term_taxonomy'), 'status' => 'supported'),
                'media' => array('label' => 'Media references', 'suffixes' => array('posts'), 'status' => 'supported')
            );
        }

        $definitions = array(
            'users' => array('label' => 'Users', 'suffixes' => array('users'), 'status' => 'supported'),
            'topics' => array('label' => 'Topics', 'suffixes' => array('topics'), 'status' => 'supported'),
            'stories' => array('label' => 'Stories', 'suffixes' => array('stories'), 'status' => 'supported'),
            'comments' => array('label' => 'Comments', 'suffixes' => array('comments'), 'status' => 'supported'),
            'staticpages' => array('label' => 'Static Pages', 'suffixes' => array('staticpage'), 'status' => 'supported'),
            'links' => array('label' => 'Links', 'suffixes' => array('links'), 'status' => 'planned'),
            'polls' => array('label' => 'Polls', 'suffixes' => array('pollquestions', 'pollquestions'), 'status' => 'planned'),
            'calendar' => array('label' => 'Calendar events', 'suffixes' => array('events'), 'status' => 'planned')
        );

        if ($cms === 'glfusion') {
            $definitions['forum'] = array(
                'label' => 'Forum topics',
                'suffixes' => array('ff_topic'),
                'status' => 'planned'
            );
            $definitions['mediagallery_albums'] = array(
                'label' => 'MediaGallery albums',
                'suffixes' => array('mg_albums'),
                'status' => 'planned'
            );
            $definitions['mediagallery_media'] = array(
                'label' => 'MediaGallery media',
                'suffixes' => array('mg_media'),
                'status' => 'planned'
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
