<?php
declare(strict_types=1);

namespace CsvIngest\Exception;

use Cake\Core\Exception\CakeException;

/**
 * The uploaded CSV is missing, expired or cannot be parsed (user-facing error).
 */
class UnreadableSourceException extends CakeException
{
}
