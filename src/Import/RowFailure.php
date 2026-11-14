<?php
declare(strict_types=1);

namespace CsvIngest\Import;

/**
 * Why a CSV row could not be persisted.
 */
final readonly class RowFailure
{
    /**
     * @param int $line 1-based line number in the CSV file.
     * @param array<string, list<string>> $messages Column title (or field) => messages.
     */
    public function __construct(
        public int $line,
        public array $messages,
    ) {
    }
}
