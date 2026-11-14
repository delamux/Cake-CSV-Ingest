<?php
declare(strict_types=1);

/**
 * Test database schema. Plugin tables mirror config/Migrations; `companies`
 * and `contacts` are the test application's import targets.
 */
return [
    [
        'table' => 'csv_ingest_presets',
        'columns' => [
            'id' => ['type' => 'integer', 'autoIncrement' => true],
            'owner_id' => ['type' => 'string', 'length' => 36, 'null' => true],
            'name' => ['type' => 'string', 'length' => 100, 'null' => false],
            'target' => ['type' => 'string', 'length' => 100, 'null' => false],
            'settings' => ['type' => 'text', 'null' => false],
            'created' => ['type' => 'datetime', 'null' => true],
            'modified' => ['type' => 'datetime', 'null' => true],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ],
    [
        'table' => 'csv_ingest_value_maps',
        'columns' => [
            'id' => ['type' => 'integer', 'autoIncrement' => true],
            'target' => ['type' => 'string', 'length' => 100, 'null' => false],
            'field' => ['type' => 'string', 'length' => 100, 'null' => false],
            'source_value' => ['type' => 'string', 'length' => 255, 'null' => false],
            'mapped_value' => ['type' => 'string', 'length' => 255, 'null' => true],
            'created' => ['type' => 'datetime', 'null' => true],
            'modified' => ['type' => 'datetime', 'null' => true],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'unique_source' => ['type' => 'unique', 'columns' => ['target', 'field', 'source_value']],
        ],
    ],
    [
        'table' => 'companies',
        'columns' => [
            'id' => ['type' => 'integer', 'autoIncrement' => true],
            'name' => ['type' => 'string', 'length' => 100, 'null' => false],
            'tax_code' => ['type' => 'string', 'length' => 20, 'null' => true],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ],
    [
        'table' => 'contacts',
        'columns' => [
            'id' => ['type' => 'integer', 'autoIncrement' => true],
            'company_id' => ['type' => 'integer', 'null' => true],
            'name' => ['type' => 'string', 'length' => 100, 'null' => false],
            'email' => ['type' => 'string', 'length' => 150, 'null' => true],
            'age' => ['type' => 'integer', 'null' => true],
            'balance' => ['type' => 'decimal', 'length' => 10, 'precision' => 2, 'null' => true],
            'is_active' => ['type' => 'boolean', 'null' => true],
            'born_on' => ['type' => 'date', 'null' => true],
            'notes' => ['type' => 'text', 'null' => true],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ],
];
