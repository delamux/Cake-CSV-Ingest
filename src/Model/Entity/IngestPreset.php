<?php
declare(strict_types=1);

namespace CsvIngest\Model\Entity;

use Cake\ORM\Entity;
use CsvIngest\Import\CsvDialect;
use CsvIngest\Import\OnMatch;

/**
 * Saved column mapping for a target table.
 *
 * @property int $id
 * @property string|null $owner_id
 * @property string $name
 * @property string $target
 * @property array<string, mixed> $settings
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class IngestPreset extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'owner_id' => true,
        'name' => true,
        'target' => true,
        'settings' => true,
    ];

    /**
     * @return array<string, string> Field => header title.
     */
    public function columnTitles(): array
    {
        $columns = $this->settings['columns'] ?? [];

        return is_array($columns) ? array_map('strval', array_filter($columns, 'is_scalar')) : [];
    }

    /**
     * @return \CsvIngest\Import\OnMatch
     */
    public function onMatch(): OnMatch
    {
        return OnMatch::tryFrom((string)($this->settings['on_match'] ?? '')) ?? OnMatch::Skip;
    }

    /**
     * @return \CsvIngest\Import\CsvDialect
     */
    public function dialect(): CsvDialect
    {
        $dialect = $this->settings['dialect'] ?? [];

        return CsvDialect::fromArray(is_array($dialect) ? $dialect : []);
    }
}
