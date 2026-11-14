<?php
declare(strict_types=1);

namespace CsvIngest\Exception;

use Cake\Core\Exception\CakeException;
use Throwable;

/**
 * Required fields (or match keys) are not assigned to any CSV column.
 */
class IncompleteMappingException extends CakeException
{
    /**
     * @param list<string> $missing Missing field names.
     * @param \Throwable|null $previous Previous.
     */
    public function __construct(private readonly array $missing, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('Required fields are not mapped: %s', implode(', ', $missing)), null, $previous);
    }

    /**
     * @return list<string>
     */
    public function missingFields(): array
    {
        return $this->missing;
    }
}
