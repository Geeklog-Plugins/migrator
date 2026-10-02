<?php

class MigratorSourceDetector
{
    public static function detect(array $tables, $sqlSample = '')
    {
        $names = array_map('strtolower', $tables);
        $sample = strtolower((string) $sqlSample);

        if (self::hasSuffix($names, 'wp_posts') || self::hasSuffix($names, 'wp_options')) {
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
