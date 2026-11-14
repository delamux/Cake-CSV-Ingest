<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Import;

use CsvIngest\Import\CsvDialect;
use CsvIngest\Import\Delimiter;
use CsvIngest\Import\Enclosure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DialectTest extends TestCase
{
    /**
     * @return array<string, array{string, \CsvIngest\Import\Delimiter}>
     */
    public static function sniffProvider(): array
    {
        return [
            'semicolon' => ["name;email;age\nAna;a@x.test;3", Delimiter::Semicolon],
            'comma' => ["name,email,age\n", Delimiter::Comma],
            'tab' => ["name\temail\tage", Delimiter::Tab],
            'pipe' => ['name|email|age', Delimiter::Pipe],
            'commas inside quotes ignored' => ["\"Doe, John\";\"Main St, 1\";x", Delimiter::Semicolon],
            'CRLF line endings' => ["a,b,c\r\n1,2,3", Delimiter::Comma],
            'empty falls back to semicolon' => ['', Delimiter::Semicolon],
            'single column falls back to semicolon' => ['name', Delimiter::Semicolon],
        ];
    }

    #[DataProvider('sniffProvider')]
    public function testSniff(string $sample, Delimiter $expected): void
    {
        $this->assertSame($expected, Delimiter::sniff($sample));
    }

    public function testCharacters(): void
    {
        $this->assertSame("\t", Delimiter::Tab->character());
        $this->assertSame("'", Enclosure::SingleQuote->character());
        $this->assertArrayHasKey('semicolon', Delimiter::options());
    }

    public function testDialectRoundTripAndTolerantParsing(): void
    {
        $dialect = new CsvDialect(Delimiter::Tab, Enclosure::SingleQuote, false);
        $this->assertEquals($dialect, CsvDialect::fromArray($dialect->toArray()));

        $fallback = CsvDialect::fromArray(['delimiter' => '../../etc', 'enclosure' => 42, 'has_header_row' => '0']);
        $this->assertSame(Delimiter::Semicolon, $fallback->delimiter);
        $this->assertSame(Enclosure::DoubleQuote, $fallback->enclosure);
        $this->assertFalse($fallback->hasHeaderRow);
    }
}
