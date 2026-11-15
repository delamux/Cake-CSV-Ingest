<?php
declare(strict_types=1);

namespace CsvIngest\Import;

/**
 * Events dispatched on the target table's event manager during a run.
 */
final class IngestEvents
{
    /**
     * Before a row is marshalled. Data: `data` (ArrayObject, mutable), `sourceRow`
     * (array<string, string> keyed by header title), `line` (int).
     * Stopping the event skips the row.
     */
    public const BEFORE_ROW_MARSHAL = 'CsvIngest.beforeRowMarshal';

    /**
     * After a row was saved. Data: `entity`, `data` (array), `sourceRow`,
     * `outcome` (RowOutcome), `line` (int). Rolled back together with the run in dry runs.
     */
    public const AFTER_ROW_PERSIST = 'CsvIngest.afterRowPersist';

    /**
     * Once, before the first row. Data: `dryRun` (bool). Every save of the run then receives the
     * options `csvIngest` (true) and `dryRun` (bool), so `beforeSave`/`afterSave` can tell imports apart
     * and defer side effects to the end of the run.
     */
    public const BEFORE_INGEST = 'CsvIngest.beforeIngest';

    /**
     * Once, after the last row, when a dry run has already been rolled back and pending values are
     * registered. Data: `report` (IngestReport), `dryRun` (bool). The place for one-off side effects
     * (rebuilding caches, files...) that must not run per row or for dry runs.
     */
    public const AFTER_INGEST = 'CsvIngest.afterIngest';
}
