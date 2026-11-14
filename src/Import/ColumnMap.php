<?php
declare(strict_types=1);

namespace CsvIngest\Import;

/**
 * Assignment of target fields to CSV column positions.
 *
 * Positions (not header titles) are used internally so files with duplicate
 * or empty headers still work. Presets store header titles instead, so they
 * keep working when columns are reordered (see {@see self::toHeaderTitles()}).
 */
final readonly class ColumnMap
{
    /**
     * @param array<string, int> $columns Field name => zero-based column index.
     */
    public function __construct(public array $columns = [])
    {
    }

    /**
     * Build from untrusted request data, dropping unknown fields and out-of-range columns.
     *
     * @param array<mixed> $raw Field => column index ('' means "do not import").
     * @param list<string> $allowedFields Field names that may be mapped.
     * @param int $columnCount Number of columns in the CSV.
     * @return self
     */
    public static function fromInput(array $raw, array $allowedFields, int $columnCount): self
    {
        $columns = [];
        foreach ($raw as $field => $index) {
            if (!in_array($field, $allowedFields, true) || $index === '' || $index === null) {
                continue;
            }
            $position = filter_var($index, FILTER_VALIDATE_INT);
            if ($position === false || $position < 0 || $position >= $columnCount) {
                continue;
            }
            $columns[(string)$field] = $position;
        }

        return new self($columns);
    }

    /**
     * Resolve header titles (from a preset) back to positions in the current file.
     *
     * @param array<string, string> $titles Field => header title.
     * @param list<string> $headers Headers of the current file.
     * @param list<string> $allowedFields Field names that may be mapped.
     * @return self
     */
    public static function fromHeaderTitles(array $titles, array $headers, array $allowedFields): self
    {
        $normalizedHeaders = array_map(self::normalize(...), $headers);
        $columns = [];
        foreach ($titles as $field => $title) {
            if (!in_array($field, $allowedFields, true)) {
                continue;
            }
            $position = array_search(self::normalize((string)$title), $normalizedHeaders, true);
            if ($position !== false) {
                $columns[$field] = $position;
            }
        }

        return new self($columns);
    }

    /**
     * Suggest a mapping by comparing header titles with field names and labels.
     *
     * @param list<\CsvIngest\Import\FieldSpec> $fields Importable fields.
     * @param list<string> $headers CSV headers.
     * @return self
     */
    public static function guess(array $fields, array $headers): self
    {
        $normalizedHeaders = array_map(self::normalize(...), $headers);
        $columns = [];
        foreach ($fields as $field) {
            foreach ([$field->name, $field->label] as $candidate) {
                $position = array_search(self::normalize($candidate), $normalizedHeaders, true);
                if ($position !== false && !in_array($position, $columns, true)) {
                    $columns[$field->name] = $position;
                    break;
                }
            }
        }

        return new self($columns);
    }

    /**
     * @param list<string> $headers CSV headers.
     * @return array<string, string> Field => header title.
     */
    public function toHeaderTitles(array $headers): array
    {
        $titles = [];
        foreach ($this->columns as $field => $position) {
            if (isset($headers[$position])) {
                $titles[$field] = $headers[$position];
            }
        }

        return $titles;
    }

    /**
     * @param string $field Field name.
     * @return bool
     */
    public function has(string $field): bool
    {
        return array_key_exists($field, $this->columns);
    }

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        return array_keys($this->columns);
    }

    /**
     * @param list<string> $required Required field names.
     * @return list<string> Required fields that are not mapped.
     */
    public function missing(array $required): array
    {
        return array_values(array_diff($required, $this->fields()));
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->columns === [];
    }

    /**
     * Lowercase, strip accents and non-alphanumerics: "Código Postal" -> "codigopostal".
     *
     * @param string $value Value to normalize.
     * @return string
     */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $ascii = function_exists('transliterator_transliterate')
            ? (string)transliterator_transliterate('Any-Latin; Latin-ASCII', $value)
            : $value;

        return (string)preg_replace('/[^a-z0-9]/', '', $ascii);
    }
}
