<?php
declare(strict_types=1);

namespace CsvIngest\Import;

use Cake\ORM\Association;
use Cake\ORM\Table;
use CsvIngest\Exception\MisconfiguredTargetException;
use CsvIngest\Model\Behavior\IngestableBehavior;
use CsvIngest\Model\Table\IngestValueMapsTable;
use InvalidArgumentException;

/**
 * Turns CSV text in a foreign-key column into the associated record's key.
 *
 * Order: (1) explicit value map with a mapped value, (2) lookup on the
 * associated table by `lookupFields[alias]` or its display field.
 * Unresolved values are collected (never persisted here) so the caller can
 * register them as pending outside of any dry-run transaction.
 */
final class ForeignKeyResolver
{
    /**
     * @var array<string, string|int|null>
     */
    private array $cache = [];

    /**
     * @var array<string, list<string>>
     */
    private array $unresolved = [];

    /**
     * @param \Cake\ORM\Table $target Ingestable target table.
     * @param \CsvIngest\Model\Table\IngestValueMapsTable|null $valueMaps Value maps, null to disable.
     */
    public function __construct(
        private readonly Table $target,
        private readonly ?IngestValueMapsTable $valueMaps = null,
    ) {
    }

    /**
     * @return string Alias used to scope value maps.
     */
    public function targetAlias(): string
    {
        return $this->target->getAlias();
    }

    /**
     * @param \CsvIngest\Import\FieldSpec $field Foreign key field.
     * @param string $raw CSV text.
     * @return string|int|null Key, or null when unresolved.
     */
    public function resolve(FieldSpec $field, string $raw): string|int|null
    {
        $association = $this->association($field);
        $text = trim($raw);
        $cacheKey = $field->name . "\0" . $text;
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $key = $this->valueMaps?->lookup($this->targetAlias(), $field->name, $text)
            ?? $this->lookup($association, $text);

        if ($key === null && !in_array($text, $this->unresolved[$field->name] ?? [], true)) {
            $this->unresolved[$field->name][] = $text;
        }

        return $this->cache[$cacheKey] = $key;
    }

    /**
     * Records of the associated table, for a mapping `<select>`.
     *
     * @param \CsvIngest\Import\FieldSpec $field Foreign key field.
     * @return array<string|int, string>
     */
    public function candidates(FieldSpec $field): array
    {
        $association = $this->association($field);
        $table = $association->getTarget();
        $labelField = $this->lookupField($association);

        /** @var array<string|int, string> */
        return $table->find('list', keyField: $this->primaryKey($table), valueField: $labelField)
            ->orderBy([$table->aliasField($labelField) => 'ASC'])
            ->toArray();
    }

    /**
     * Values that could not be resolved during this run.
     *
     * @return array<string, list<string>> Field => distinct CSV texts.
     */
    public function unresolved(): array
    {
        return $this->unresolved;
    }

    /**
     * @param \Cake\ORM\Association $association BelongsTo association.
     * @param string $text Trimmed CSV text.
     * @return string|int|null
     */
    private function lookup(Association $association, string $text): string|int|null
    {
        if ($text === '') {
            return null;
        }
        $table = $association->getTarget();
        $primaryKey = $this->primaryKey($table);
        $row = $table->find()
            ->select([$primaryKey => $table->aliasField($primaryKey)])
            ->where([$table->aliasField($this->lookupField($association)) => $text])
            ->disableHydration()
            ->first();

        $key = is_array($row) ? ($row[$primaryKey] ?? null) : null;

        return is_int($key) || is_string($key) ? $key : null;
    }

    /**
     * @param \CsvIngest\Import\FieldSpec $field Field.
     * @return \Cake\ORM\Association
     */
    private function association(FieldSpec $field): Association
    {
        if ($field->association === null || !$this->target->associations()->has($field->association)) {
            throw new InvalidArgumentException(sprintf('Field "%s" is not a BelongsTo foreign key.', $field->name));
        }

        return $this->target->getAssociation($field->association);
    }

    /**
     * @param \Cake\ORM\Association $association Association.
     * @return string
     */
    private function lookupField(Association $association): string
    {
        $behavior = $this->target->getBehavior('Ingestable');
        $configured = $behavior instanceof IngestableBehavior ? $behavior->lookupField($association->getName()) : null;
        if ($configured !== null) {
            return $configured;
        }
        $display = $association->getTarget()->getDisplayField();

        return is_array($display) ? (string)reset($display) : $display;
    }

    /**
     * @param \Cake\ORM\Table $table Table.
     * @return string
     */
    private function primaryKey(Table $table): string
    {
        $primaryKey = $table->getPrimaryKey();
        if (!is_string($primaryKey)) {
            throw new MisconfiguredTargetException(sprintf(
                'Table "%s" has a composite primary key; it cannot be resolved from CSV text.',
                $table->getAlias(),
            ));
        }

        return $primaryKey;
    }
}
