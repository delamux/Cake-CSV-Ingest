<?php
declare(strict_types=1);

namespace CsvIngest\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class CompaniesFixture extends TestFixture
{
    public array $records = [
        ['name' => 'Acme', 'tax_code' => 'A111'],
        ['name' => 'Globex', 'tax_code' => 'B123'],
    ];
}
