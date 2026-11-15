<?php
declare(strict_types=1);

namespace CsvIngest\Import;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Log\Log;
use Cake\ORM\Exception\RolledbackTransactionException;
use Cake\ORM\Table;
use Cake\Utility\Hash;
use CsvIngest\Exception\IncompleteMappingException;
use CsvIngest\Exception\MisconfiguredTargetException;
use CsvIngest\IngestSettings;
use CsvIngest\Model\Behavior\IngestableBehavior;
use CsvIngest\Model\Table\IngestValueMapsTable;
use PDOException;
use function Cake\I18n\__d;

/**
 * Imports the rows of a CSV source into an Ingestable table.
 *
 * Each row is saved atomically; a failing row never stops the run. A dry run
 * wraps everything in one transaction and rolls it back. Unresolved foreign
 * key texts are registered as pending value maps after the run (also in dry runs).
 */
final class IngestRunner
{
    private IngestableBehavior $behavior;

    private ValueCaster $caster;

    /**
     * Whether the current run is a dry run (passed to every save as the `dryRun` option).
     */
    private bool $dryRun = false;

    /**
     * @param \Cake\ORM\Table $target Table with the Ingestable behavior.
     * @param \CsvIngest\Model\Table\IngestValueMapsTable|null $valueMaps Value maps (null disables them).
     * @param \CsvIngest\Import\ValueCaster|null $caster Caster (defaults from settings).
     * @param int $maxRows Max rows to process (0 = unlimited).
     */
    public function __construct(
        private readonly Table $target,
        private readonly ?IngestValueMapsTable $valueMaps = null,
        ?ValueCaster $caster = null,
        private readonly int $maxRows = 0,
    ) {
        $behavior = $target->hasBehavior('Ingestable') ? $target->getBehavior('Ingestable') : null;
        if (!$behavior instanceof IngestableBehavior) {
            throw new MisconfiguredTargetException(sprintf(
                'Table "%s" must use the CsvIngest.Ingestable behavior.',
                $target->getAlias(),
            ));
        }
        $this->behavior = $behavior;
        $this->caster = $caster ?? new ValueCaster(IngestSettings::booleanLiterals(), IngestSettings::dateFormats());
    }

    /**
     * @param \CsvIngest\Import\CsvSource $source Parsed file.
     * @param \CsvIngest\Import\ColumnMap $map Field => column assignments.
     * @param \CsvIngest\Import\OnMatch $onMatch Policy for existing records.
     * @param bool $dryRun Roll everything back at the end.
     * @return \CsvIngest\Import\IngestReport
     * @throws \CsvIngest\Exception\IncompleteMappingException
     */
    public function run(
        CsvSource $source,
        ColumnMap $map,
        OnMatch $onMatch = OnMatch::Skip,
        bool $dryRun = false,
    ): IngestReport {
        $fields = [];
        $required = [];
        foreach ($this->behavior->ingestFields() as $spec) {
            $fields[$spec->name] = $spec;
            if ($spec->required) {
                $required[] = $spec->name;
            }
        }
        $missing = $map->missing($required);
        if ($missing !== []) {
            throw new IncompleteMappingException($missing);
        }

        $this->dryRun = $dryRun;
        $this->target->dispatchEvent(IngestEvents::BEFORE_INGEST, ['dryRun' => $dryRun]);

        $resolver = new ForeignKeyResolver($this->target, $this->valueMaps);
        $report = new IngestReport($dryRun);
        $connection = $this->target->getConnection();
        $savePoints = $connection->isSavePointsEnabled();
        if ($dryRun) {
            $connection->enableSavePoints(true);
            $connection->begin();
        }

        try {
            $processed = 0;
            foreach ($source->rows() as $line => $row) {
                if ($this->maxRows > 0 && $processed >= $this->maxRows) {
                    $report->markTruncated();
                    break;
                }
                $processed++;
                $this->ingestRow($source, $map, $fields, $resolver, $onMatch, $report, $line, $row);
            }
        } finally {
            if ($dryRun) {
                $connection->rollback();
                $connection->enableSavePoints($savePoints);
            }
        }

        $report->noteUnresolved($resolver->unresolved());
        $this->registerPending($resolver->unresolved());
        $this->target->dispatchEvent(IngestEvents::AFTER_INGEST, ['report' => $report, 'dryRun' => $dryRun]);

        return $report;
    }

    /**
     * @param \CsvIngest\Import\CsvSource $source Source.
     * @param \CsvIngest\Import\ColumnMap $map Map.
     * @param array<string, \CsvIngest\Import\FieldSpec> $fields Specs by name.
     * @param \CsvIngest\Import\ForeignKeyResolver $resolver Resolver.
     * @param \CsvIngest\Import\OnMatch $onMatch Policy.
     * @param \CsvIngest\Import\IngestReport $report Report.
     * @param int $line Line number.
     * @param list<string> $row Positional row.
     * @return void
     */
    private function ingestRow(
        CsvSource $source,
        ColumnMap $map,
        array $fields,
        ForeignKeyResolver $resolver,
        OnMatch $onMatch,
        IngestReport $report,
        int $line,
        array $row,
    ): void {
        $titles = [];
        $data = [];
        $keyErrors = [];
        foreach ($map->columns as $name => $position) {
            $spec = $fields[$name];
            $titles[$name] = $this->title($source, $position, $spec);
            $raw = $row[$position] ?? '';

            if (!$spec->isForeignKey()) {
                $data[$name] = $this->caster->cast($raw, $spec->type);
                continue;
            }
            if (trim($raw) === '') {
                $data[$name] = null;
                continue;
            }
            $key = $resolver->resolve($spec, $raw);
            if ($key === null) {
                $keyErrors[$titles[$name]][] = __d(
                    'csv_ingest',
                    'No {0} matches "{1}". Assign it in the pending values list.',
                    $spec->label,
                    trim($raw),
                );
                continue;
            }
            $data[$name] = $key;
        }
        if ($keyErrors !== []) {
            $report->fail(new RowFailure($line, $keyErrors));

            return;
        }

        $sourceRow = $source->associate($row);
        $mutable = new ArrayObject($data);
        $event = $this->target->dispatchEvent(IngestEvents::BEFORE_ROW_MARSHAL, [
            'data' => $mutable,
            'sourceRow' => $sourceRow,
            'line' => $line,
        ]);
        if ($event->isStopped()) {
            $report->record(RowOutcome::Skipped);

            return;
        }
        $data = $mutable->getArrayCopy();

        [$entity, $outcome] = $this->prepareEntity($data, $onMatch);
        if ($outcome === null) {
            $report->fail(new RowFailure($line, $this->ambiguityMessages($titles, $fields)));

            return;
        }
        if ($entity === null) {
            $report->record(RowOutcome::Skipped);

            return;
        }

        if (!$this->persist($entity)) {
            $report->fail(new RowFailure($line, $this->messages($entity, $titles, $fields)));

            return;
        }

        $report->record($outcome);
        $this->target->dispatchEvent(IngestEvents::AFTER_ROW_PERSIST, [
            'entity' => $entity,
            'data' => $data,
            'sourceRow' => $sourceRow,
            'outcome' => $outcome,
            'line' => $line,
        ]);
    }

    /**
     * @param array<string, mixed> $data Row data.
     * @param \CsvIngest\Import\OnMatch $onMatch Policy.
     * @return array{0: \Cake\Datasource\EntityInterface|null, 1: \CsvIngest\Import\RowOutcome|null}
     *   Outcome null: the row matches more than one existing record (nothing is changed).
     */
    private function prepareEntity(array $data, OnMatch $onMatch): array
    {
        $existing = null;
        if ($this->behavior->matchFields() !== []) {
            $matches = $this->target->find('ingestMatch', values: $data)->limit(2)->all()->toList();
            if (count($matches) > 1) {
                return [null, null];
            }
            $existing = $matches[0] ?? null;
        }

        if (!$existing instanceof EntityInterface) {
            return [$this->target->newEntity($data, $this->marshalOptions($data)), RowOutcome::Created];
        }

        return match ($onMatch) {
            OnMatch::Skip => [null, RowOutcome::Skipped],
            OnMatch::Replace => [
                $this->target->patchEntity($existing, $data, $this->marshalOptions($data)),
                RowOutcome::Replaced,
            ],
            OnMatch::Patch => [
                $this->target->patchEntity(
                    $existing,
                    $nonBlank = array_filter($data, static fn(mixed $v): bool => $v !== null && $v !== ''),
                    $this->marshalOptions($nonBlank),
                ),
                RowOutcome::Patched,
            ],
        };
    }

    /**
     * @param array<string, mixed> $data Data being marshalled.
     * @return array<string, mixed>
     */
    private function marshalOptions(array $data): array
    {
        return [
            'validate' => $this->behavior->validationSet(),
            'accessibleFields' => array_fill_keys(array_keys($data), true),
        ];
    }

    /**
     * @param \Cake\Datasource\EntityInterface $entity Entity.
     * @return bool
     */
    private function persist(EntityInterface $entity): bool
    {
        if ($entity->hasErrors()) {
            return false;
        }
        try {
            return $this->target->save($entity, [
                'atomic' => true,
                'csvIngest' => true,
                'dryRun' => $this->dryRun,
            ]) !== false;
        } catch (PDOException | RolledbackTransactionException $e) {
            Log::warning(sprintf(
                'CsvIngest: row could not be saved in %s: %s',
                $this->target->getAlias(),
                $e->getMessage(),
            ));
            $entity->setError('_database', [__d('csv_ingest', 'The database rejected this row.')]);

            return false;
        }
    }

    /**
     * @param \Cake\Datasource\EntityInterface $entity Failed entity.
     * @param array<string, string> $titles Field => column title.
     * @param array<string, \CsvIngest\Import\FieldSpec> $fields Specs.
     * @return array<string, list<string>>
     */
    private function messages(EntityInterface $entity, array $titles, array $fields): array
    {
        $messages = [];
        foreach ($entity->getErrors() as $field => $errors) {
            $key = $titles[$field] ?? (isset($fields[$field]) ? $fields[$field]->label : (string)$field);
            $flat = is_array($errors) ? Hash::flatten($errors) : [$errors];
            foreach ($flat as $message) {
                $messages[$key][] = (string)$message;
            }
        }

        return $messages;
    }

    /**
     * Failure messages for a row whose match key finds several records, under each match column.
     *
     * @param array<string, string> $titles Field => column title.
     * @param array<string, \CsvIngest\Import\FieldSpec> $fields Specs.
     * @return array<string, list<string>>
     */
    private function ambiguityMessages(array $titles, array $fields): array
    {
        $message = __d('csv_ingest', 'More than one existing record matches this row; none of them was changed.');
        $messages = [];
        foreach ($this->behavior->matchFields() as $field) {
            $key = $titles[$field] ?? (isset($fields[$field]) ? $fields[$field]->label : $field);
            $messages[$key][] = $message;
        }

        return $messages;
    }

    /**
     * @param \CsvIngest\Import\CsvSource $source Source.
     * @param int $position Column index.
     * @param \CsvIngest\Import\FieldSpec $spec Field.
     * @return string
     */
    private function title(CsvSource $source, int $position, FieldSpec $spec): string
    {
        $title = $source->headers()[$position] ?? '';

        return $title !== '' ? $title : $spec->label;
    }

    /**
     * @param array<string, list<string>> $unresolved Field => texts.
     * @return void
     */
    private function registerPending(array $unresolved): void
    {
        if ($this->valueMaps === null) {
            return;
        }
        foreach ($unresolved as $field => $texts) {
            foreach ($texts as $text) {
                $this->valueMaps->registerPending($this->target->getAlias(), $field, $text);
            }
        }
    }
}
