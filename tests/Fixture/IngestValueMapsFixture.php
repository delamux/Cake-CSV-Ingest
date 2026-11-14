<?php
declare(strict_types=1);

namespace CsvIngest\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class IngestValueMapsFixture extends TestFixture
{
    public string $table = 'csv_ingest_value_maps';

    public array $records = [
        [
            'target' => 'Contacts',
            'field' => 'company_id',
            'source_value' => 'ACME Inc',
            'mapped_value' => null,
            'created' => '2026-01-01 10:00:00',
            'modified' => '2026-01-01 10:00:00',
        ],
        [
            'target' => 'Contacts',
            'field' => 'company_id',
            'source_value' => 'Glbx',
            'mapped_value' => '2',
            'created' => '2026-01-01 10:00:00',
            'modified' => '2026-01-01 10:00:00',
        ],
        [
            'target' => 'Companies',
            'field' => 'country_id',
            'source_value' => 'ES',
            'mapped_value' => null,
            'created' => '2026-01-01 10:00:00',
            'modified' => '2026-01-01 10:00:00',
        ],
    ];
}
