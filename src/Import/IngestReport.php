<?php
declare(strict_types=1);

namespace CsvIngest\Import;

/**
 * Summary of an import run. Mutable while the run is in progress.
 */
final class IngestReport
{
    /**
     * @var array<string, int>
     */
    private array $counts = [];

    /**
     * @var list<\CsvIngest\Import\RowFailure>
     */
    private array $failures = [];

    private bool $truncated = false;

    /**
     * @param bool $dryRun Whether changes were rolled back.
     */
    public function __construct(public readonly bool $dryRun = false)
    {
        foreach (RowOutcome::cases() as $outcome) {
            $this->counts[$outcome->value] = 0;
        }
    }

    /**
     * @param \CsvIngest\Import\RowOutcome $outcome Outcome to count.
     * @return void
     */
    public function record(RowOutcome $outcome): void
    {
        $this->counts[$outcome->value]++;
    }

    /**
     * @param \CsvIngest\Import\RowFailure $failure Failure details.
     * @return void
     */
    public function fail(RowFailure $failure): void
    {
        $this->record(RowOutcome::Failed);
        $this->failures[] = $failure;
    }

    /**
     * Flag that processing stopped at the configured row limit.
     *
     * @return void
     */
    public function markTruncated(): void
    {
        $this->truncated = true;
    }

    /**
     * @param \CsvIngest\Import\RowOutcome $outcome Outcome.
     * @return int
     */
    public function count(RowOutcome $outcome): int
    {
        return $this->counts[$outcome->value];
    }

    /**
     * @return int
     */
    public function total(): int
    {
        return array_sum($this->counts);
    }

    /**
     * @return list<\CsvIngest\Import\RowFailure>
     */
    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * @return bool
     */
    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }

    /**
     * @return bool
     */
    public function isTruncated(): bool
    {
        return $this->truncated;
    }
}
