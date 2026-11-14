<?php
declare(strict_types=1);

namespace CsvIngest\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ContactsFixture extends TestFixture
{
    public array $records = [
        [
            'company_id' => 1,
            'name' => 'Ana',
            'email' => 'ana@example.test',
            'age' => 30,
            'balance' => '10.50',
            'is_active' => true,
            'born_on' => '1996-01-15',
            'notes' => 'original',
        ],
    ];
}
