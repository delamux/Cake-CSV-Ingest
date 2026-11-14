<?php
declare(strict_types=1);

namespace CsvIngest\Exception;

use Cake\Core\Exception\CakeException;

/**
 * The target table is not set up correctly for CSV ingestion (developer error).
 */
class MisconfiguredTargetException extends CakeException
{
}
