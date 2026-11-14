<?php
declare(strict_types=1);

namespace CsvIngest\Import;

use DateTimeImmutable;

/**
 * Normalizes raw CSV strings into values the ORM marshaller understands.
 *
 * Deliberately conservative: when a value cannot be interpreted it is returned
 * unchanged so table validation reports a meaningful error instead of the
 * value being silently coerced (e.g. "abc" never becomes 0).
 */
final class ValueCaster
{
    /**
     * @param array<string, bool> $booleanLiterals Lowercase literal => boolean.
     * @param list<string> $dateFormats Accepted input formats for date/datetime columns.
     */
    public function __construct(
        private readonly array $booleanLiterals = [],
        private readonly array $dateFormats = ['Y-m-d'],
    ) {
    }

    /**
     * @param string $raw Raw CSV value.
     * @param string $type Schema column type.
     * @return mixed
     */
    public function cast(string $raw, string $type): mixed
    {
        $value = trim($raw);
        if ($value === '') {
            return in_array($type, ['string', 'text', 'char'], true) ? '' : null;
        }

        return match ($type) {
            'boolean' => $this->toBoolean($value),
            'decimal', 'float' => $this->toNumber($value),
            'integer', 'biginteger', 'smallinteger', 'tinyinteger' => $this->toInteger($value),
            'date' => $this->toDate($value, 'Y-m-d'),
            'datetime', 'datetimefractional', 'timestamp', 'timestampfractional' => $this->toDate($value, 'Y-m-d H:i:s'),
            default => $value,
        };
    }

    /**
     * @param string $value Trimmed value.
     * @return string|bool
     */
    private function toBoolean(string $value): string|bool
    {
        $key = mb_strtolower($value);
        if (array_key_exists($key, $this->booleanLiterals)) {
            return $this->booleanLiterals[$key];
        }

        return match ($key) {
            '1' => true,
            '0' => false,
            default => $value,
        };
    }

    /**
     * Accepts "1234.5", "1234,5", "1.234,56", "1,234.56", "1 234,56", "-3".
     *
     * The right-most separator is the decimal mark when both appear. A lone
     * separator followed by exactly three digits and repeated ("1.234.567") is
     * treated as thousands grouping.
     *
     * @param string $value Trimmed value.
     * @return string Numeric string, or the input when not numeric.
     */
    private function toNumber(string $value): string
    {
        $clean = (string)preg_replace('/[\s\x{00A0}\x{202F}\']/u', '', $value);
        $lastDot = strrpos($clean, '.');
        $lastComma = strrpos($clean, ',');

        if ($lastDot !== false && $lastComma !== false) {
            [$decimal, $grouping] = $lastComma > $lastDot ? [',', '.'] : ['.', ','];
            $clean = str_replace([$grouping, $decimal], ['', '.'], $clean);
        } elseif ($lastDot !== false || $lastComma !== false) {
            $separator = $lastDot !== false ? '.' : ',';
            $isGrouping = substr_count($clean, $separator) > 1
                && preg_match('/^-?\d{1,3}(' . preg_quote($separator, '/') . '\d{3})+$/', $clean) === 1;
            $clean = $isGrouping ? str_replace($separator, '', $clean) : str_replace($separator, '.', $clean);
        }

        return is_numeric($clean) ? $clean : $value;
    }

    /**
     * @param string $value Trimmed value.
     * @return string
     */
    private function toInteger(string $value): string
    {
        $clean = (string)preg_replace('/[\s\x{00A0}\x{202F}.,\']/u', '', $value);

        return preg_match('/^-?\d+$/', $clean) === 1 ? $clean : $value;
    }

    /**
     * @param string $value Trimmed value.
     * @param string $outputFormat Format understood by the ORM.
     * @return string
     */
    private function toDate(string $value, string $outputFormat): string
    {
        foreach ($this->dateFormats as $format) {
            foreach ([$format, $format . ' H:i', $format . ' H:i:s'] as $candidate) {
                $date = DateTimeImmutable::createFromFormat('!' . $candidate, $value);
                $errors = DateTimeImmutable::getLastErrors();
                $hasErrors = $errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
                if ($date !== false && !$hasErrors) {
                    return $date->format($outputFormat);
                }
            }
        }

        return $value;
    }
}
