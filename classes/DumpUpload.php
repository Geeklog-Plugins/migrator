<?php

class MigratorDumpUpload
{
    const MAX_UNCOMPRESSED_BYTES = 268435456; // 256 MiB

    public static function normalize(array $upload, $destinationDir)
    {
        if (!isset($upload['tmp_name'], $upload['name'], $upload['error'])) {
            throw new RuntimeException('Invalid upload payload.');
        }

        if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed with error code ' . (int) $upload['error'] . '.');
        }

        $originalName = basename((string) $upload['name']);
        $format = self::detectFormat($originalName);

        if ($format === '') {
            throw new RuntimeException('Unsupported dump format.');
        }

        $destinationDir = rtrim((string) $destinationDir, "/\\") . DIRECTORY_SEPARATOR;
        if (!is_dir($destinationDir) || !is_writable($destinationDir)) {
            throw new RuntimeException('Migrator data directory is not writable.');
        }

        $base = date('Ymd-His') . '-' . bin2hex(random_bytes(6));
        $sqlName = $base . '.sql';
        $sqlPath = $destinationDir . $sqlName;

        if ($format === 'sql') {
            if (!move_uploaded_file($upload['tmp_name'], $sqlPath)) {
                throw new RuntimeException('The uploaded SQL file could not be stored.');
            }
        } elseif ($format === 'gz') {
            self::extractGzip($upload['tmp_name'], $sqlPath);
        } elseif ($format === 'zip') {
            self::extractZip($upload['tmp_name'], $sqlPath);
        }

        if (!is_file($sqlPath) || filesize($sqlPath) === 0) {
            @unlink($sqlPath);
            throw new RuntimeException('The normalized SQL dump is empty.');
        }

        return array(
            'sql_path' => $sqlPath,
            'stored_name' => $sqlName,
            'original_name' => $originalName,
            'format' => $format
        );
    }

    public static function detectFormat($filename)
    {
        $name = strtolower((string) $filename);

        if (substr($name, -7) === '.sql.gz') {
            return 'gz';
        }

        if (substr($name, -4) === '.sql') {
            return 'sql';
        }

        if (substr($name, -4) === '.zip') {
            return 'zip';
        }

        return '';
    }

    private static function extractGzip($source, $target)
    {
        if (!function_exists('gzopen')) {
            throw new RuntimeException('Gzip support is not available on this PHP installation.');
        }

        $in = @gzopen($source, 'rb');
        if ($in === false) {
            throw new RuntimeException('The gzip archive could not be opened.');
        }

        $out = @fopen($target, 'wb');
        if ($out === false) {
            gzclose($in);
            throw new RuntimeException('The normalized SQL file could not be created.');
        }

        $written = 0;

        try {
            while (!gzeof($in)) {
                $chunk = gzread($in, 1048576);
                if ($chunk === false) {
                    throw new RuntimeException('The gzip archive could not be read.');
                }

                $written += strlen($chunk);
                if ($written > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new RuntimeException('The uncompressed SQL dump exceeds the 256 MiB safety limit.');
                }

                if ($chunk !== '' && fwrite($out, $chunk) === false) {
                    throw new RuntimeException('The normalized SQL file could not be written.');
                }
            }
        } catch (Exception $e) {
            fclose($out);
            gzclose($in);
            @unlink($target);
            throw $e;
        }

        fclose($out);
        gzclose($in);
    }

    private static function extractZip($source, $target)
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZIP support is not available on this PHP installation.');
        }

        $zip = new ZipArchive();
        if ($zip->open($source) !== true) {
            throw new RuntimeException('The ZIP archive could not be opened.');
        }

        $sqlIndexes = array();

        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = (string) $zip->getNameIndex($i);
            $normalized = str_replace('\\', '/', $name);

            if ($normalized === ''
                || strpos($normalized, '../') !== false
                || strpos($normalized, '/..') !== false
                || substr($normalized, 0, 1) === '/'
                || preg_match('/^[A-Za-z]:\//', $normalized)
            ) {
                $zip->close();
                throw new RuntimeException('The ZIP archive contains an unsafe path.');
            }

            if (substr($normalized, -1) === '/') {
                continue;
            }

            if (strtolower(substr($normalized, -4)) === '.sql') {
                $sqlIndexes[] = $i;
            }
        }

        if (count($sqlIndexes) !== 1) {
            $zip->close();
            throw new RuntimeException('The ZIP archive must contain exactly one SQL dump.');
        }

        $stat = $zip->statIndex($sqlIndexes[0]);
        if (!is_array($stat) || !isset($stat['size'])) {
            $zip->close();
            throw new RuntimeException('The SQL entry size could not be determined.');
        }

        if ((int) $stat['size'] > self::MAX_UNCOMPRESSED_BYTES) {
            $zip->close();
            throw new RuntimeException('The uncompressed SQL dump exceeds the 256 MiB safety limit.');
        }

        $stream = $zip->getStream($zip->getNameIndex($sqlIndexes[0]));
        if ($stream === false) {
            $zip->close();
            throw new RuntimeException('The SQL entry in the ZIP archive could not be opened.');
        }

        $out = @fopen($target, 'wb');
        if ($out === false) {
            fclose($stream);
            $zip->close();
            throw new RuntimeException('The normalized SQL file could not be created.');
        }

        $written = 0;

        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 1048576);
                if ($chunk === false) {
                    throw new RuntimeException('The SQL entry in the ZIP archive could not be read.');
                }

                $written += strlen($chunk);
                if ($written > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new RuntimeException('The uncompressed SQL dump exceeds the 256 MiB safety limit.');
                }

                if ($chunk !== '' && fwrite($out, $chunk) === false) {
                    throw new RuntimeException('The normalized SQL file could not be written.');
                }
            }
        } catch (Exception $e) {
            fclose($out);
            fclose($stream);
            $zip->close();
            @unlink($target);
            throw $e;
        }

        fclose($out);
        fclose($stream);
        $zip->close();
    }
}
