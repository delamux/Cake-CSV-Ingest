<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Import;

use CsvIngest\Import\ColumnMap;
use CsvIngest\Import\FieldSpec;
use PHPUnit\Framework\TestCase;

class ColumnMapTest extends TestCase
{
    public function testFromInputDropsUnknownFieldsBlanksAndOutOfRange(): void
    {
        $map = ColumnMap::fromInput(
            ['name' => '0', 'email' => '', 'age' => '9', 'hacker' => '1', 'balance' => 'x', 'notes' => 2],
            ['name', 'email', 'age', 'balance', 'notes'],
            3,
        );

        $this->assertSame(['name' => 0, 'notes' => 2], $map->columns);
        $this->assertSame(['email'], $map->missing(['name', 'email']));
        $this->assertTrue($map->has('name'));
        $this->assertFalse($map->isEmpty());
    }

    public function testHeaderTitlesRoundTripSurvivesReordering(): void
    {
        $map = new ColumnMap(['name' => 0, 'email' => 1]);
        $titles = $map->toHeaderTitles(['Nombre', 'E-mail']);
        $this->assertSame(['name' => 'Nombre', 'email' => 'E-mail'], $titles);

        $reordered = ColumnMap::fromHeaderTitles($titles, ['e-mail ', 'Edad', 'NOMBRE'], ['name', 'email']);
        $this->assertSame(['name' => 2, 'email' => 0], $reordered->columns);
    }

    public function testGuessMatchesNamesAndLabelsIgnoringAccentsAndCase(): void
    {
        $fields = [
            new FieldSpec('name', 'Name', 'string'),
            new FieldSpec('born_on', 'Fecha de nacimiento', 'date'),
            new FieldSpec('company_id', 'Company', 'integer', association: 'Companies'),
            new FieldSpec('notes', 'Notes', 'text'),
        ];
        $headers = ['NAME', 'Fecha de Nacimiento', 'company', 'name'];

        $map = ColumnMap::guess($fields, $headers);

        $this->assertSame(['name' => 0, 'born_on' => 1, 'company_id' => 2], $map->columns);
    }

    public function testNormalize(): void
    {
        $this->assertSame('codigopostal', ColumnMap::normalize(' Código Postal '));
        $this->assertSame('email', ColumnMap::normalize('E-mail'));
    }
}
