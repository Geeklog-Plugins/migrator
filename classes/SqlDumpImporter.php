<?php
// +--------------------------------------------------------------------------+
// | Migrator Plugin - Geeklog                                                |
// +--------------------------------------------------------------------------+
// | SqlDumpImporter.php                                                      |
// |                                                                          |
// | Safe SQL dump staging importer.                                          |
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

class MigratorSqlDumpImporter
{
    private $tableMap = array();
    private $executed = 0;
    private $skipped = 0;

    public function import($file)
    {
        global $_DB_table_prefix;

        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Empty or unreadable SQL dump.');
        }

        $sourceTables = $this->discoverTables($sql);
        if (empty($sourceTables)) {
            throw new RuntimeException('No importable CREATE TABLE or INSERT INTO statements were found.');
        }

        foreach ($sourceTables as $sourceTable) {
            $this->tableMap[$sourceTable] = $this->stagingName($sourceTable, $_DB_table_prefix);
        }

        $this->dropMappedTables();

        $statements = $this->splitStatements($sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }

            $kind = $this->statementKind($statement);
            if ($kind !== 'create' && $kind !== 'insert') {
                ++$this->skipped;
                continue;
            }

            $rewritten = $this->rewriteStatement($statement);
            if ($rewritten === '') {
                ++$this->skipped;
                continue;
            }

            $result = DB_query($rewritten, 1);
            if ($result === false) {
                throw new RuntimeException('A source SQL statement could not be staged.');
            }

            ++$this->executed;
        }

        return array(
            'table_map' => $this->tableMap,
            'source_tables' => array_keys($this->tableMap),
            'executed' => $this->executed,
            'skipped' => $this->skipped,
            'sample' => substr($sql, 0, 262144)
        );
    }

    private function discoverTables($sql)
    {
        $tables = array();

        if (preg_match_all('/(?:CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT\s+INTO)\s+([^\s(]+)/i', $sql, $matches)) {
            foreach ($matches[1] as $table) {
                $table = $this->normalizeSourceTable($table);
                if ($table !== '') {
                    $tables[$table] = true;
                }
            }
        }

        return array_keys($tables);
    }

    private function normalizeSourceTable($table)
    {
        $table = trim((string) $table);
        $table = str_replace(chr(96), '', $table);

        if (strpos($table, '.') !== false) {
            $parts = explode('.', $table);
            $table = end($parts);
        }

        return preg_replace('/[^A-Za-z0-9_$-]/', '', $table);
    }

    private function stagingName($sourceTable, $dbPrefix)
    {
        $safe = strtolower(preg_replace('/[^A-Za-z0-9_]/', '_', $sourceTable));
        $base = $dbPrefix . 'migrator_src_' . $safe;

        if (strlen($base) <= 60) {
            return $base;
        }

        return substr($base, 0, 51) . '_' . substr(sha1($sourceTable), 0, 8);
    }

    private function dropMappedTables()
    {
        foreach ($this->tableMap as $target) {
            DB_query('DROP TABLE IF EXISTS ' . $target);
        }
    }

    private function statementKind($statement)
    {
        if (preg_match('/^CREATE\s+TABLE\b/i', ltrim($statement))) {
            return 'create';
        }

        if (preg_match('/^INSERT\s+INTO\b/i', ltrim($statement))) {
            return 'insert';
        }

        return 'skip';
    }

    private function rewriteStatement($statement)
    {
        if (preg_match('/\b(DROP\s+DATABASE|CREATE\s+DATABASE|GRANT\s+|REVOKE\s+|LOAD\s+DATA|OUTFILE|INFILE)\b/i', $statement)) {
            return '';
        }

        foreach ($this->tableMap as $source => $target) {
            $quotedSource = preg_quote($source, '/');

            $statement = preg_replace(
                '/(CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+)' . chr(96) . '?' . $quotedSource . chr(96) . '?/i',
                '$1' . $target,
                $statement,
                1
            );

            $statement = preg_replace(
                '/(INSERT\s+INTO\s+)' . chr(96) . '?' . $quotedSource . chr(96) . '?/i',
                '$1' . $target,
                $statement,
                1
            );
        }

        return $statement;
    }

    private function splitStatements($sql)
    {
        $statements = array();
        $buffer = '';
        $length = strlen($sql);
        $quote = '';
        $escaped = false;
        $lineComment = false;
        $blockComment = false;

        for ($i = 0; $i < $length; ++$i) {
            $char = $sql[$i];
            $next = ($i + 1 < $length) ? $sql[$i + 1] : '';

            if ($lineComment) {
                if ($char === "\n") {
                    $lineComment = false;
                    $buffer .= $char;
                }
                continue;
            }

            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $blockComment = false;
                    ++$i;
                }
                continue;
            }

            if ($quote === '') {
                if ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) {
                    $lineComment = true;
                    ++$i;
                    continue;
                }

                if ($char === '#') {
                    $lineComment = true;
                    continue;
                }

                if ($char === '/' && $next === '*') {
                    $blockComment = true;
                    ++$i;
                    continue;
                }

                if ($char === "'" || $char === '"' || $char === chr(96)) {
                    $quote = $char;
                    $buffer .= $char;
                    continue;
                }

                if ($char === ';') {
                    $statements[] = $buffer;
                    $buffer = '';
                    continue;
                }
            } else {
                if ($escaped) {
                    $escaped = false;
                    $buffer .= $char;
                    continue;
                }

                if ($char === '\\' && $quote !== chr(96)) {
                    $escaped = true;
                    $buffer .= $char;
                    continue;
                }

                if ($char === $quote) {
                    if (($quote === "'" || $quote === '"') && $next === $quote) {
                        $buffer .= $char . $next;
                        ++$i;
                        continue;
                    }

                    $quote = '';
                }
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }
}
