<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Import;

use CsvIngest\Exception\UnreadableSourceException;
use CsvIngest\Import\CsvDialect;
use CsvIngest\Import\CsvSource;
use CsvIngest\Import\Delimiter;
use CsvIngest\Import\Enclosure;
use PHPUnit\Framework\TestCase;

class CsvSourceTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->files, 'is_file'));
    }

    private function file(string $contents): string
    {
        $path = (string)tempnam(sys_get_temp_dir(), 'ci_src_');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }

    public function testDuplicateAndBlankHeadersAreTolerated(): void
    {
        $source = CsvSource::open($this->file("Name;Name; \nAna;Ana B;x\n"), new CsvDialect());

        $this->assertSame(['Name', 'Name', ''], $source->headers());
        $this->assertSame(3, $source->columnCount());
        $this->assertSame([2 => ['Ana', 'Ana B', 'x']], iterator_to_array($source->rows()));
        $this->assertSame(['Name' => 'Ana', '' => 'x'], $source->associate(['Ana', 'Ana B', 'x']));
    }

    public function testRowsArePaddedTruncatedAndKeyedByLineSkippingEmptyLines(): void
    {
        $csv = "a,b,c\n1,2\n\n4,5,6,7\n";
        $source = CsvSource::open($this->file($csv), new CsvDialect(Delimiter::Comma));

        $this->assertSame(
            [2 => ['1', '2', ''], 4 => ['4', '5', '6']],
            iterator_to_array($source->rows()),
        );
    }

    public function testWithoutHeaderRowGeneratesColumnTitlesAndKeepsFirstRow(): void
    {
        $source = CsvSource::open($this->file("'x;y';2\n3;4\n"), new CsvDialect(
            Delimiter::Semicolon,
            Enclosure::SingleQuote,
            false,
        ));

        $this->assertSame(['Column 1', 'Column 2'], $source->headers());
        $this->assertSame([['x;y', '2'], ['3', '4']], $source->preview(10));
        $this->assertSame([['x;y', '2']], $source->preview(1));
    }

    public function testBomIsSkipped(): void
    {
        $source = CsvSource::open($this->file("\xEF\xBB\xBFname;age\nAna;3\n"), new CsvDialect());

        $this->assertSame(['name', 'age'], $source->headers());
    }

    public function testMissingFileThrows(): void
    {
        $this->expectException(UnreadableSourceException::class);
        CsvSource::open('/nonexistent/file.csv', new CsvDialect());
    }

    public function testEmptyFileThrows(): void
    {
        $this->expectException(UnreadableSourceException::class);
        CsvSource::open($this->file(''), new CsvDialect());
    }
}
