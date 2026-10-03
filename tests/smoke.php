<?php

$queries = array();
$_DB_table_prefix = 'gl_';

function DB_query($sql, $ignoreErrors = 0)
{
    global $queries;
    $queries[] = $sql;

    return true;
}

require_once __DIR__ . '/../classes/SqlDumpImporter.php';
require_once __DIR__ . '/../classes/SourceDetector.php';

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assertTrueValue($value, $message)
{
    if (!$value) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$sql = <<<'SQL'
-- WordPress-like dump with a semicolon inside a string
DROP TABLE IF EXISTS `custom_posts`;
CREATE TABLE `custom_posts` (
  `ID` bigint NOT NULL,
  `post_title` text NOT NULL
) ENGINE=InnoDB;
INSERT INTO `custom_posts` (`ID`, `post_title`) VALUES
(1, 'Hello; world');
SET NAMES utf8mb4;
SQL;

$tmp = tempnam(sys_get_temp_dir(), 'migrator-smoke-');
file_put_contents($tmp, $sql);

$importer = new MigratorSqlDumpImporter();
$result = $importer->import($tmp);
@unlink($tmp);

assertSameValue(2, $result['executed'], 'SQL importer must execute only CREATE TABLE and INSERT INTO.');
assertTrueValue($result['skipped'] >= 2, 'SQL importer must skip DROP/SET statements.');
assertSameValue(
    'gl_migrator_src_custom_posts',
    $result['table_map']['custom_posts'],
    'Source tables must be rewritten to the isolated staging prefix.'
);

$joined = implode("\n", $queries);
assertTrueValue(
    strpos($joined, 'CREATE TABLE gl_migrator_src_custom_posts') !== false,
    'CREATE TABLE must target the staging table.'
);
assertTrueValue(
    strpos($joined, 'INSERT INTO gl_migrator_src_custom_posts') !== false,
    'INSERT INTO must target the staging table.'
);
assertTrueValue(
    strpos($joined, 'DROP TABLE IF EXISTS custom_posts') === false,
    'Source DROP TABLE must never execute against its original name.'
);

$wordpress = MigratorSourceDetector::detect(
    array('abc_users', 'abc_posts', 'abc_options', 'abc_usermeta')
);
assertSameValue('wordpress', $wordpress['cms'], 'WordPress custom prefixes must be detected.');

$glfusion = MigratorSourceDetector::detect(
    array('gl_users', 'gl_stories', 'gl_plugins', 'gl_mg_albums', 'gl_mg_media')
);
assertSameValue('glfusion', $glfusion['cms'], 'glFusion MediaGallery signatures must be detected.');

$legacy = MigratorSourceDetector::detect(
    array('gl_users', 'gl_stories', 'gl_plugins', 'gl_topics')
);
assertSameValue('legacy_geeklog', $legacy['cms'], 'Legacy Geeklog core signatures must be detected.');

$unknown = MigratorSourceDetector::detect(array('random_table'));
assertSameValue('unknown', $unknown['cms'], 'Unknown schemas must remain unknown.');

echo "Migrator smoke tests passed.\n";

$fixtures = array(
    'geeklog_legacy_test.sql' => 'legacy_geeklog',
    'glfusion_test.sql' => 'glfusion',
    'wordpress_test.sql' => 'wordpress'
);

foreach ($fixtures as $fixture => $expectedCms) {
    $queries = array();
    $file = __DIR__ . '/fixtures/' . $fixture;

    assertTrueValue(is_file($file), 'Fixture file missing: ' . $fixture);

    $fixtureImporter = new MigratorSqlDumpImporter();
    $fixtureResult = $fixtureImporter->import($file);
    $fixtureDetected = MigratorSourceDetector::detect(
        $fixtureResult['source_tables'],
        $fixtureResult['sample']
    );

    assertSameValue(
        $expectedCms,
        $fixtureDetected['cms'],
        'Fixture CMS detection failed for ' . $fixture
    );

    assertTrueValue(
        count($fixtureResult['source_tables']) > 0,
        'Fixture did not stage any source tables: ' . $fixture
    );
}

echo "Migrator fixture smoke tests passed.\n";

