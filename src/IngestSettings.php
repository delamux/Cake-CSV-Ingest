<?php
declare(strict_types=1);

namespace CsvIngest;

use Cake\Core\Configure;

/**
 * Typed accessor for the `CsvIngest.*` configuration.
 *
 * Defaults live in `config/csv_ingest.php` and are merged *under* any values
 * the application wrote before the plugin bootstrap ran.
 */
final class IngestSettings
{
    /**
     * @param string $key Dotted key below `CsvIngest`.
     * @param mixed $default Fallback.
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Configure::read('CsvIngest.' . $key, $default);
    }

    /**
     * @return string Absolute directory for uploaded files (with trailing separator).
     */
    public static function storagePath(): string
    {
        $path = (string)self::get('storagePath', TMP . 'csv_ingest');

        return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * @return list<string>
     */
    public static function encodings(): array
    {
        return array_values((array)self::get('encodings', ['UTF-8', 'Windows-1252', 'ISO-8859-1']));
    }

    /**
     * @return array<string, bool>
     */
    public static function booleanLiterals(): array
    {
        /** @var array<string, bool> */
        return (array)self::get('booleanLiterals', []);
    }

    /**
     * @return list<string>
     */
    public static function dateFormats(): array
    {
        return array_values((array)self::get('dateFormats', ['Y-m-d']));
    }

    /**
     * @return int Bytes.
     */
    public static function maxFileSize(): int
    {
        return (int)self::get('maxFileSize', 10 * 1024 * 1024);
    }

    /**
     * @return int Max data rows processed per run; 0 means unlimited.
     */
    public static function maxRows(): int
    {
        return (int)self::get('maxRows', 0);
    }

    /**
     * @return int
     */
    public static function previewRows(): int
    {
        return (int)self::get('previewRows', 5);
    }

    /**
     * @return int Seconds an uploaded file is kept.
     */
    public static function uploadTtl(): int
    {
        return (int)self::get('uploadTtl', 86400);
    }

    /**
     * @return int Seconds passed to set_time_limit() during a run; 0 leaves PHP's limit alone.
     */
    public static function timeLimit(): int
    {
        return (int)self::get('timeLimit', 0);
    }

    /**
     * @return string Translation domain used for field labels.
     */
    public static function labelDomain(): string
    {
        return (string)self::get('labelDomain', 'default');
    }
}
