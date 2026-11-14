<?php
declare(strict_types=1);

/**
 * CsvIngest default configuration.
 *
 * Override any key from the application BEFORE the plugin bootstraps, e.g. in
 * config/app.php: `'CsvIngest' => ['maxRows' => 5000]`. Application values win.
 */
return [
    // Directory for uploaded files between wizard steps.
    'storagePath' => TMP . 'csv_ingest',

    // Seconds an uploaded file is kept before being purged.
    'uploadTtl' => 86400,

    // Max upload size in bytes.
    'maxFileSize' => 10 * 1024 * 1024,

    // Max data rows processed per run (0 = unlimited).
    'maxRows' => 0,

    // Rows shown in the mapping screen preview.
    'previewRows' => 5,

    // set_time_limit() for a run in seconds (0 = leave PHP default).
    'timeLimit' => 0,

    // Candidate source encodings; files are normalized to UTF-8.
    'encodings' => ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ISO-8859-15'],

    // Lowercase CSV literals accepted for boolean columns ("1"/"0" always work).
    'booleanLiterals' => [
        'yes' => true, 'no' => false,
        'true' => true, 'false' => false,
        'y' => true, 'n' => false,
        'si' => true, 'sí' => true,
        'verdadero' => true, 'falso' => false,
        'active' => true, 'inactive' => false,
        'activo' => true, 'inactivo' => false,
        'x' => true,
    ],

    // Accepted input formats for date/datetime columns (tried in order; time part optional).
    'dateFormats' => ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'],

    // Translation domain for field labels (labels from the behavior `labels` option win).
    'labelDomain' => 'default',

    // Optional table alias for preset owners, e.g. 'Users'. Enables the Owners association.
    'ownerTable' => null,

    // Optional Closure(\Cake\Http\ServerRequest): string|int|null returning the current owner id.
    // Default: the `identity` request attribute (cakephp/authentication) identifier.
    'ownerResolver' => null,
];
