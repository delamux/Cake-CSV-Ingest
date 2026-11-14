<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Import;

use CsvIngest\Import\ValueCaster;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValueCasterTest extends TestCase
{
    private ValueCaster $caster;

    protected function setUp(): void
    {
        $this->caster = new ValueCaster(
            ['yes' => true, 'no' => false, 'sí' => true],
            ['Y-m-d', 'd/m/Y'],
        );
    }

    /**
     * @return array<string, array{string, string, mixed}>
     */
    public static function castProvider(): array
    {
        return [
            'blank string column stays empty string' => ['  ', 'string', ''],
            'blank decimal becomes null, not zero' => ['', 'decimal', null],
            'blank boolean becomes null' => ['', 'boolean', null],
            'string is trimmed' => ['  Ana ', 'string', 'Ana'],
            'boolean literal yes' => ['YES', 'boolean', true],
            'boolean literal no' => ['no', 'boolean', false],
            'boolean multibyte literal' => ['Sí', 'boolean', true],
            'boolean 1' => ['1', 'boolean', true],
            'boolean 0' => ['0', 'boolean', false],
            'unknown boolean left for validation' => ['maybe', 'boolean', 'maybe'],
            'decimal plain dot' => ['1234.5', 'decimal', '1234.5'],
            'decimal comma' => ['1234,5', 'decimal', '1234.5'],
            'decimal es grouping' => ['1.234,56', 'decimal', '1234.56'],
            'decimal en grouping' => ['1,234.56', 'decimal', '1234.56'],
            'decimal spaced grouping' => ['1 234,56', 'decimal', '1234.56'],
            'decimal repeated dot grouping' => ['1.234.567', 'float', '1234567'],
            'decimal negative' => ['-3,5', 'decimal', '-3.5'],
            'decimal not numeric left untouched' => ['abc', 'decimal', 'abc'],
            'zero stays zero' => ['0', 'decimal', '0'],
            'integer with grouping' => ['1.000', 'integer', '1000'],
            'integer invalid left untouched' => ['12a', 'integer', '12a'],
            'date iso' => ['2026-03-12', 'date', '2026-03-12'],
            'date european' => ['12/03/2026', 'date', '2026-03-12'],
            'datetime european with time' => ['12/03/2026 08:30', 'datetime', '2026-03-12 08:30:00'],
            'impossible date left for validation' => ['31/02/2026', 'date', '31/02/2026'],
            'unknown type passthrough' => ['x', 'json', 'x'],
        ];
    }

    #[DataProvider('castProvider')]
    public function testCast(string $raw, string $type, mixed $expected): void
    {
        $this->assertSame($expected, $this->caster->cast($raw, $type));
    }
}
