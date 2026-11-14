<?php
declare(strict_types=1);

namespace CsvIngest\Import;

/**
 * How a CSV file must be parsed.
 */
final readonly class CsvDialect
{
    /**
     * @param \CsvIngest\Import\Delimiter $delimiter Field separator.
     * @param \CsvIngest\Import\Enclosure $enclosure Field enclosure.
     * @param bool $hasHeaderRow Whether the first record holds column titles.
     */
    public function __construct(
        public Delimiter $delimiter = Delimiter::Semicolon,
        public Enclosure $enclosure = Enclosure::DoubleQuote,
        public bool $hasHeaderRow = true,
    ) {
    }

    /**
     * Build from untrusted input (form data, session, preset settings).
     *
     * Unknown values fall back to defaults instead of throwing.
     *
     * @param array<string, mixed> $data Raw values.
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Delimiter::tryFrom((string)($data['delimiter'] ?? '')) ?? Delimiter::Semicolon,
            Enclosure::tryFrom((string)($data['enclosure'] ?? '')) ?? Enclosure::DoubleQuote,
            filter_var($data['has_header_row'] ?? true, FILTER_VALIDATE_BOOL),
        );
    }

    /**
     * @return array{delimiter: string, enclosure: string, has_header_row: bool}
     */
    public function toArray(): array
    {
        return [
            'delimiter' => $this->delimiter->value,
            'enclosure' => $this->enclosure->value,
            'has_header_row' => $this->hasHeaderRow,
        ];
    }
}
