<?php
declare(strict_types=1);

namespace CsvIngest\Import;

use CsvIngest\Exception\UnreadableSourceException;
use Generator;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use function Cake\I18n\__d;

/**
 * Read-only view over a stored CSV file.
 *
 * Records are read positionally (no header offset), so duplicate or blank
 * header titles never break parsing.
 */
final class CsvSource
{
    /**
     * @var list<string>
     */
    private array $headers;

    private int $columnCount;

    /**
     * @param \League\Csv\Reader<array<array-key, mixed>> $reader Configured reader.
     * @param \CsvIngest\Import\CsvDialect $dialect Dialect used.
     */
    private function __construct(
        private readonly Reader $reader,
        public readonly CsvDialect $dialect,
    ) {
        $first = $this->firstRecord();
        $this->columnCount = count($first);
        $this->headers = $dialect->hasHeaderRow
            ? array_map(static fn(string $title): string => trim($title), $first)
            : array_map(
                static fn(int $i): string => __d('csv_ingest', 'Column {0}', $i + 1),
                array_keys($first),
            );
    }

    /**
     * @param string $path Absolute path of a UTF-8 CSV file.
     * @param \CsvIngest\Import\CsvDialect $dialect Parsing options.
     * @return self
     * @throws \CsvIngest\Exception\UnreadableSourceException
     */
    public static function open(string $path, CsvDialect $dialect): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new UnreadableSourceException(__d('csv_ingest', 'The uploaded file is no longer available.'));
        }

        try {
            $reader = Reader::fromPath($path, 'r');
            $reader->setDelimiter($dialect->delimiter->character());
            $reader->setEnclosure($dialect->enclosure->character());
            $reader->skipInputBOM();
            $reader->skipEmptyRecords();

            $source = new self($reader, $dialect);
        } catch (CsvException $e) {
            throw new UnreadableSourceException(
                __d('csv_ingest', 'The file could not be parsed: {0}', $e->getMessage()),
                null,
                $e,
            );
        }

        if ($source->columnCount === 0) {
            throw new UnreadableSourceException(__d('csv_ingest', 'The file is empty.'));
        }

        return $source;
    }

    /**
     * @return list<string> Column titles (or generated "Column N" titles).
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @return int
     */
    public function columnCount(): int
    {
        return $this->columnCount;
    }

    /**
     * Data rows keyed by their 1-based line number in the file.
     *
     * Rows are padded/truncated to the header width.
     *
     * @return \Generator<int, list<string>>
     */
    public function rows(): Generator
    {
        $skipFirst = $this->dialect->hasHeaderRow;
        foreach ($this->reader->getRecords() as $offset => $record) {
            if ($skipFirst) {
                $skipFirst = false;
                continue;
            }
            yield (int)$offset + 1 => $this->fit($record);
        }
    }

    /**
     * @param int $limit Max rows.
     * @return list<list<string>>
     */
    public function preview(int $limit): array
    {
        $rows = [];
        foreach ($this->rows() as $row) {
            if (count($rows) >= $limit) {
                break;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Pair a positional row with header titles (first title wins on duplicates).
     *
     * @param list<string> $row Positional row.
     * @return array<string, string>
     */
    public function associate(array $row): array
    {
        $assoc = [];
        foreach ($this->headers as $i => $title) {
            $assoc[$title] ??= $row[$i] ?? '';
        }

        return $assoc;
    }

    /**
     * @return list<string>
     */
    private function firstRecord(): array
    {
        foreach ($this->reader->getRecords() as $record) {
            return array_map(strval(...), array_values($record));
        }

        return [];
    }

    /**
     * @param array<mixed> $record Raw record.
     * @return list<string>
     */
    private function fit(array $record): array
    {
        $values = array_map(static fn(mixed $v): string => (string)$v, array_values($record));

        return array_pad(array_slice($values, 0, $this->columnCount), $this->columnCount, '');
    }
}
