<?php
declare(strict_types=1);

namespace CsvIngest\Model\Entity;

use Cake\ORM\Entity;

/**
 * Translation of a CSV text into a foreign-key value.
 *
 * @property int $id
 * @property string $target
 * @property string $field
 * @property string $source_value
 * @property string|null $mapped_value
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class IngestValueMap extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'target' => true,
        'field' => true,
        'source_value' => true,
        'mapped_value' => true,
    ];

    /**
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->mapped_value === null || $this->mapped_value === '';
    }
}
