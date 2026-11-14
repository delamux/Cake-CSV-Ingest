<?php
declare(strict_types=1);

namespace CsvIngest\Import;

use function Cake\I18n\__d;

/**
 * Result of processing a single CSV row.
 */
enum RowOutcome: string
{
    case Created = 'created';
    case Replaced = 'replaced';
    case Patched = 'patched';
    case Skipped = 'skipped';
    case Failed = 'failed';

    /**
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => __d('csv_ingest', 'Created'),
            self::Replaced => __d('csv_ingest', 'Replaced'),
            self::Patched => __d('csv_ingest', 'Patched'),
            self::Skipped => __d('csv_ingest', 'Skipped'),
            self::Failed => __d('csv_ingest', 'Failed'),
        };
    }
}
