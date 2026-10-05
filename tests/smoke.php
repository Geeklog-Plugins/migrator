<?php
// +--------------------------------------------------------------------------+
// | Migrator Plugin - Geeklog                                                |
// +--------------------------------------------------------------------------+
// | smoke.php                                                                |
// |                                                                          |
// | Lightweight smoke tests for Migrator core helpers.                       |
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
require_once __DIR__ . '/../classes/DumpUpload.php';

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



assertSameValue('sql', MigratorDumpUpload::detectFormat('dump.sql'), 'SQL format detection failed.');
assertSameValue('gz', MigratorDumpUpload::detectFormat('dump.sql.gz'), 'Gzip format detection failed.');
assertSameValue('zip', MigratorDumpUpload::detectFormat('dump.zip'), 'ZIP format detection failed.');
assertSameValue('', MigratorDumpUpload::detectFormat('dump.tar.gz'), 'Unsupported archive format must be rejected.');

$fixtureSql = __DIR__ . '/fixtures/geeklog_legacy_test.sql';
$fixtureContent = file_get_contents($fixtureSql);
assertTrueValue($fixtureContent !== false && $fixtureContent !== '', 'Fixture content unavailable.');

if (function_exists('gzencode') && function_exists('gzopen')) {
    $gzFile = tempnam(sys_get_temp_dir(), 'migrator-gz-') . '.sql.gz';
    file_put_contents($gzFile, gzencode($fixtureContent));

    $ref = new ReflectionClass('MigratorDumpUpload');
    $method = $ref->getMethod('extractGzip');
    $method->setAccessible(true);

    $gzOut = tempnam(sys_get_temp_dir(), 'migrator-gz-out-') . '.sql';
    $method->invoke(null, $gzFile, $gzOut);

    assertSameValue(
        sha1($fixtureContent),
        sha1(file_get_contents($gzOut)),
        'Gzip extraction must preserve SQL content.'
    );

    @unlink($gzFile);
    @unlink($gzOut);
}

if (class_exists('ZipArchive')) {
    $zipFile = tempnam(sys_get_temp_dir(), 'migrator-zip-') . '.zip';
    $zip = new ZipArchive();
    assertTrueValue($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'ZIP test archive could not be created.');
    $zip->addFromString('nested/geeklog.sql', $fixtureContent);
    $zip->close();

    $ref = new ReflectionClass('MigratorDumpUpload');
    $method = $ref->getMethod('extractZip');
    $method->setAccessible(true);

    $zipOut = tempnam(sys_get_temp_dir(), 'migrator-zip-out-') . '.sql';
    $method->invoke(null, $zipFile, $zipOut);

    assertSameValue(
        sha1($fixtureContent),
        sha1(file_get_contents($zipOut)),
        'ZIP extraction must preserve SQL content.'
    );

    @unlink($zipFile);
    @unlink($zipOut);
}

echo "Migrator compressed upload smoke tests passed.\n";
