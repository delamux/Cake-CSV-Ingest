<?php
declare(strict_types=1);

namespace CsvIngest\Import;

use function Cake\I18n\__d;

/**
 * Field separator of a CSV file.
 *
 * Backed by a stable slug (not the raw character) so values survive HTML
 * forms, sessions and JSON without escaping issues (e.g. the tab character).
 */
enum Delimiter: string
{
    case Semicolon = 'semicolon';
    case Comma = 'comma';
    case Tab = 'tab';
    case Pipe = 'pipe';

    /**
     * The literal character used by the CSV parser.
     *
     * @return string
     */
    public function character(): string
    {
        return match ($this) {
            self::Semicolon => ';',
            self::Comma => ',',
            self::Tab => "\t",
            self::Pipe => '|',
        };
    }

    /**
     * Human readable, translated label.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Semicolon => __d('csv_ingest', 'Semicolon (;)'),
            self::Comma => __d('csv_ingest', 'Comma (,)'),
            self::Tab => __d('csv_ingest', 'Tab'),
            self::Pipe => __d('csv_ingest', 'Pipe (|)'),
        };
    }

    /**
     * Options for a `<select>` element.
     *
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

    /**
     * Guess the delimiter from the first line of a CSV sample.
     *
     * Counts each candidate outside quoted sections and returns the most
     * frequent one; falls back to semicolon (spreadsheet default in es/eu locales).
     *
     * @param string $sample First bytes of the file.
     * @return self
     */
    public static function sniff(string $sample): self
    {
        $firstLine = strtok(str_replace("\r\n", "\n", $sample), "\n");
        if ($firstLine === false || $firstLine === '') {
            return self::Semicolon;
        }
        $unquoted = (string)preg_replace('/"[^"]*"/', '', $firstLine);

        $best = self::Semicolon;
        $bestCount = 0;
        foreach (self::cases() as $case) {
            $count = substr_count($unquoted, $case->character());
            if ($count > $bestCount) {
                $best = $case;
                $bestCount = $count;
            }
        }

        return $best;
    }
}
