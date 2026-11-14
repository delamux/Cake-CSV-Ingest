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
}
