<?php
declare(strict_types=1);

namespace CsvIngest\Import;

use function Cake\I18n\__d;

/**
 * Character used to wrap CSV fields that contain separators or line breaks.
 */
enum Enclosure: string
{
    case DoubleQuote = 'double';
    case SingleQuote = 'single';

    /**
     * @return string
     */
    public function character(): string
    {
        return match ($this) {
            self::DoubleQuote => '"',
            self::SingleQuote => "'",
        };
    }

    /**
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::DoubleQuote => __d('csv_ingest', 'Double quote (")'),
            self::SingleQuote => __d('csv_ingest', "Single quote (')"),
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
