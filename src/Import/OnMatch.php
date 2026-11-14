<?php
declare(strict_types=1);

namespace CsvIngest\Import;

use function Cake\I18n\__d;

/**
 * What to do when a CSV row matches an existing record.
 */
enum OnMatch: string
{
    /** Overwrite every mapped field, blanks included. */
    case Replace = 'replace';

    /** Overwrite only the fields that are not blank in the CSV row. */
    case Patch = 'patch';

    /** Leave the existing record untouched. */
    case Skip = 'skip';

    /**
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Replace => __d('csv_ingest', 'Replace: overwrite all mapped fields'),
            self::Patch => __d('csv_ingest', 'Patch: overwrite only non-blank values'),
            self::Skip => __d('csv_ingest', 'Skip: keep the existing record'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
