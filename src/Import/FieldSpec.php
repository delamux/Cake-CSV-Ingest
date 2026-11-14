<?php
declare(strict_types=1);

namespace CsvIngest\Import;

/**
 * Describes one target-table column that can receive CSV data.
 */
final readonly class FieldSpec
{
    /**
     * @param string $name Column name.
     * @param string $label Translated label shown to users.
     * @param string $type Schema type (string, integer, decimal, boolean, date...).
     * @param bool $required Must be mapped to a CSV column.
     * @param bool $isMatchKey Part of the key used to find existing records.
     * @param string|null $association BelongsTo association alias when the column is a foreign key.
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type,
        public bool $required = false,
        public bool $isMatchKey = false,
        public ?string $association = null,
    ) {
    }

    /**
     * @return bool
     */
    public function isForeignKey(): bool
    {
        return $this->association !== null;
    }
}
