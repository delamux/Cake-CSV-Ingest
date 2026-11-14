<?php
declare(strict_types=1);

namespace CsvIngest\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class IngestPresetsFixture extends TestFixture
{
    public string $table = 'csv_ingest_presets';

    public array $records = [
        [
            'owner_id' => null,
            'name' => 'Shared layout',
            'target' => 'Contacts',
            'settings' => '{"columns":{"name":"Nombre","email":"Correo"},"on_match":"patch","dialect":{"delimiter":"semicolon","enclosure":"double","has_header_row":true}}',
            'created' => '2026-01-01 10:00:00',
            'modified' => '2026-01-01 10:00:00',
        ],
        [
            'owner_id' => '7',
            'name' => 'Mine',
            'target' => 'Contacts',
            'settings' => '{"columns":{"name":"Name"},"on_match":"replace","dialect":{}}',
            'created' => '2026-01-01 10:00:00',
            'modified' => '2026-01-01 10:00:00',
        ],
        [
            'owner_id' => '8',
            'name' => 'Someone else',
            'target' => 'Contacts',
            'settings' => '{"columns":{}}',
            'created' => '2026-01-01 10:00:00',
            'modified' => '2026-01-01 10:00:00',
        ],
        [
            'owner_id' => null,
            'name' => 'Other target',
            'target' => 'Companies',
            'settings' => '{"columns":{}}',
            'created' => '2026-01-01 10:00:00',
            'modified' => '2026-01-01 10:00:00',
        ],
    ];
}
