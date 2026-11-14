<?php
declare(strict_types=1);

namespace CsvIngest\Model\Behavior;

use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Behavior;
use Cake\ORM\Query\SelectQuery;
use Cake\Utility\Inflector;
use Closure;
use CsvIngest\Exception\MisconfiguredTargetException;
use CsvIngest\Import\FieldSpec;
use CsvIngest\IngestSettings;

use function Cake\I18n\__d;

/**
 * Marks a table as a CSV ingestion target and describes how rows map onto it.
 *
 * Configuration:
 * - `fields` (list<string>|Closure(\Cake\ORM\Table): list<string>, required)
 *   Columns that may receive CSV data. Anything else is never written.
 * - `labels` (array<string, string>) Field => label overrides.
 * - `matchOn` (string|list<string>|false|null) Columns identifying an existing
 *   record. `null` uses the display field when importable; `false` always creates.
 * - `validation` (string|bool) Validation set passed to newEntity/patchEntity.
 * - `lookupFields` (array<string, string>) BelongsTo alias => column used to
 *   resolve foreign keys from CSV text (defaults to the target display field).
 *
 * Exposes the `ingestMatch` finder. A table may define its own
 * `findIngestMatch(SelectQuery $query, array $values)` to customize matching.
 */
class IngestableBehavior extends Behavior
{
    /**
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'fields' => [],
        'labels' => [],
        'matchOn' => null,
        'validation' => 'default',
        'lookupFields' => [],
        'implementedFinders' => [
            'ingestMatch' => 'findIngestMatch',
        ],
        'implementedMethods' => [],
    ];

    /**
     * @var list<\CsvIngest\Import\FieldSpec>|null
     */
    private ?array $fieldSpecs = null;

    /**
     * Importable fields, resolved and validated against the schema.
     *
     * @return list<\CsvIngest\Import\FieldSpec>
     * @throws \CsvIngest\Exception\MisconfiguredTargetException
     */
    public function ingestFields(): array
    {
        if ($this->fieldSpecs !== null) {
            return $this->fieldSpecs;
        }

        $names = $this->fieldNames();
        $schema = $this->table()->getSchema();
        $matchOn = $this->matchFields();
        $required = $this->requiredByValidation($names);
        $associations = $this->foreignKeyAssociations();
        $labels = (array)$this->getConfig('labels');

        $specs = [];
        foreach ($names as $name) {
            $type = $schema->getColumnType($name);
            if ($type === null) {
                throw new MisconfiguredTargetException(sprintf(
                    'Ingestable: column "%s" does not exist in table "%s".',
                    $name,
                    $this->table()->getTable(),
                ));
            }
            $isMatchKey = in_array($name, $matchOn, true);
            $specs[] = new FieldSpec(
                name: $name,
                label: (string)($labels[$name] ?? $this->defaultLabel($name)),
                type: $type,
                required: $isMatchKey || in_array($name, $required, true),
                isMatchKey: $isMatchKey,
                association: $associations[$name] ?? null,
            );
        }

        return $this->fieldSpecs = $specs;
    }

    /**
     * @param string $name Field name.
     * @return \CsvIngest\Import\FieldSpec|null
     */
    public function ingestField(string $name): ?FieldSpec
    {
        foreach ($this->ingestFields() as $spec) {
            if ($spec->name === $name) {
                return $spec;
            }
        }

        return null;
    }

    /**
     * Columns used to detect existing records; empty when matching is disabled.
     *
     * @return list<string>
     */
    public function matchFields(): array
    {
        $matchOn = $this->getConfig('matchOn');
        if ($matchOn === false) {
            return [];
        }
        $names = $this->fieldNames();

        if ($matchOn === null) {
            $display = array_values((array)$this->table()->getDisplayField());

            return array_diff($display, $names) === [] ? $display : [];
        }

        $matchOn = array_values((array)$matchOn);
        $unknown = array_diff($matchOn, $names);
        if ($unknown !== []) {
            throw new MisconfiguredTargetException(sprintf(
                'Ingestable: matchOn field(s) "%s" must also be listed in "fields".',
                implode('", "', $unknown),
            ));
        }

        return $matchOn;
    }

    /**
     * @return string|bool Validation option for the marshaller.
     */
    public function validationSet(): string|bool
    {
        $validation = $this->getConfig('validation');

        return is_string($validation) || is_bool($validation) ? $validation : 'default';
    }

    /**
     * Column of the associated table used to resolve CSV text into a key.
     *
     * @param string $association BelongsTo alias.
     * @return string|null Null means "use the target display field".
     */
    public function lookupField(string $association): ?string
    {
        $field = $this->getConfig('lookupFields.' . $association);

        return is_string($field) ? $field : null;
    }

    /**
     * Find a record matching the CSV row on the configured key.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Cake\Datasource\EntityInterface|array<string, mixed>> $query Query.
     * @param array<string, mixed> $values Marshalled row values.
     * @return \Cake\ORM\Query\SelectQuery<\Cake\Datasource\EntityInterface|array<string, mixed>>
     */
    public function findIngestMatch(SelectQuery $query, array $values = []): SelectQuery
    {
        $fields = $this->matchFields();
        if ($fields === []) {
            return $query->where('1 = 0');
        }
        foreach ($fields as $field) {
            $column = $this->table()->aliasField($field);
            $value = $values[$field] ?? null;
            $query->where($value === null ? [$column . ' IS' => null] : [$column => $value]);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private function fieldNames(): array
    {
        $fields = $this->getConfig('fields');
        if ($fields instanceof Closure) {
            $fields = $fields($this->table());
        }
        $fields = array_values(array_unique(array_filter((array)$fields, 'is_string')));
        if ($fields === []) {
            throw new MisconfiguredTargetException(sprintf(
                'Ingestable: the "fields" option is required on table "%s".',
                $this->table()->getAlias(),
            ));
        }

        return $fields;
    }

    /**
     * @param list<string> $names Field names.
     * @return list<string>
     */
    private function requiredByValidation(array $names): array
    {
        $set = $this->validationSet();
        if ($set === false) {
            return [];
        }
        $validator = $this->table()->getValidator($set === true ? 'default' : $set);

        $required = [];
        foreach ($names as $name) {
            if (!$validator->hasField($name)) {
                continue;
            }
            $presence = $validator->field($name)->isPresenceRequired();
            if ($presence === true || $presence === 'create') {
                $required[] = $name;
            }
        }

        return $required;
    }

    /**
     * @return array<string, string> Foreign key column => association alias.
     */
    private function foreignKeyAssociations(): array
    {
        $map = [];
        foreach ($this->table()->associations()->getByType(['BelongsTo']) as $association) {
            assert($association instanceof BelongsTo);
            $foreignKey = $association->getForeignKey();
            if (is_string($foreignKey)) {
                $map[$foreignKey] = $association->getName();
            }
        }

        return $map;
    }

    /**
     * @param string $name Field name.
     * @return string
     */
    private function defaultLabel(string $name): string
    {
        $base = (string)preg_replace('/_id$/', '', $name);

        return __d(IngestSettings::labelDomain(), Inflector::humanize($base));
    }
}
