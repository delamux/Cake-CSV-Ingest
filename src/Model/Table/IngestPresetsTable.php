<?php
declare(strict_types=1);

namespace CsvIngest\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use CsvIngest\IngestSettings;
use CsvIngest\Model\Entity\IngestPreset;
use function Cake\I18n\__d;

/**
 * Saved column mappings ("presets") per target table, optionally owned by a user.
 *
 * `settings` JSON shape:
 * ```
 * {"columns": {"email": "E-mail", "name": "Nombre"}, "on_match": "patch",
 *  "dialect": {"delimiter": "semicolon", "enclosure": "double", "has_header_row": true}}
 * ```
 *
 * @method \CsvIngest\Model\Entity\IngestPreset newEmptyEntity()
 * @method \CsvIngest\Model\Entity\IngestPreset newEntity(array<string, mixed> $data, array<string, mixed> $options = [])
 * @method \CsvIngest\Model\Entity\IngestPreset get(mixed $primaryKey, array<string, mixed>|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \CsvIngest\Model\Entity\IngestPreset patchEntity(\Cake\Datasource\EntityInterface $entity, array<string, mixed> $data, array<string, mixed> $options = [])
 * @method \CsvIngest\Model\Entity\IngestPreset|false save(\Cake\Datasource\EntityInterface $entity, array<string, mixed> $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class IngestPresetsTable extends Table
{
    /**
     * @param array<string, mixed> $config Config.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('csv_ingest_presets');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->getSchema()->setColumnType('settings', 'json');
        $this->addBehavior('Timestamp');

        $ownerTable = IngestSettings::get('ownerTable');
        if (is_string($ownerTable) && $ownerTable !== '') {
            $this->belongsTo('Owners', [
                'className' => $ownerTable,
                'foreignKey' => 'owner_id',
                'joinType' => 'LEFT',
            ]);
        }
    }

    /**
     * @param \Cake\Validation\Validator $validator Validator.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->scalar('name')
            ->maxLength('name', 100)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->scalar('target')
            ->maxLength('target', 100)
            ->requirePresence('target', 'create')
            ->notEmptyString('target')
            ->scalar('owner_id')
            ->maxLength('owner_id', 36)
            ->allowEmptyString('owner_id')
            ->array('settings')
            ->requirePresence('settings', 'create');
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules Rules.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(
            ['target', 'name', 'owner_id'],
            ['allowMultipleNulls' => false, 'message' => __d('csv_ingest', 'A preset with this name already exists.')],
        ));

        return $rules;
    }

    /**
     * Presets a user may use for a target: shared ones plus their own.
     *
     * @param \Cake\ORM\Query\SelectQuery<\CsvIngest\Model\Entity\IngestPreset> $query Query.
     * @param string|null $target Target alias (null = all targets).
     * @param string|int|null $ownerId Current user id.
     * @return \Cake\ORM\Query\SelectQuery<\CsvIngest\Model\Entity\IngestPreset>
     */
    public function findVisible(
        SelectQuery $query,
        ?string $target = null,
        string|int|null $ownerId = null,
    ): SelectQuery {
        $owner = [$this->aliasField('owner_id') . ' IS' => null];
        if ($ownerId !== null) {
            $owner = ['OR' => [$owner, $this->aliasField('owner_id') => (string)$ownerId]];
        }
        if ($target !== null && $target !== '') {
            $query->where([$this->aliasField('target') => $target]);
        }

        return $query
            ->where($owner)
            ->orderBy([$this->aliasField('target') => 'ASC', $this->aliasField('name') => 'ASC']);
    }

    /**
     * @param string $target Target alias.
     * @param string $name Preset name.
     * @param string|int|null $ownerId Owner.
     * @return \CsvIngest\Model\Entity\IngestPreset|null
     */
    public function findOwned(string $target, string $name, string|int|null $ownerId): ?IngestPreset
    {
        $owner = $ownerId === null
            ? [$this->aliasField('owner_id') . ' IS' => null]
            : [$this->aliasField('owner_id') => (string)$ownerId];

        /** @var \CsvIngest\Model\Entity\IngestPreset|null */
        return $this->find()
            ->where([$this->aliasField('target') => $target, $this->aliasField('name') => trim($name)])
            ->where($owner)
            ->first();
    }

    /**
     * Create or update a preset (same target + name + owner).
     *
     * @param string $target Target alias.
     * @param string $name Preset name.
     * @param array<string, mixed> $settings Settings payload.
     * @param string|int|null $ownerId Owner.
     * @return \CsvIngest\Model\Entity\IngestPreset Entity (check `hasErrors()`).
     */
    public function remember(string $target, string $name, array $settings, string|int|null $ownerId): IngestPreset
    {
        $preset = $this->findOwned($target, $name, $ownerId) ?? $this->newEmptyEntity();
        $preset = $this->patchEntity($preset, [
            'target' => $target,
            'name' => trim($name),
            'owner_id' => $ownerId === null ? null : (string)$ownerId,
            'settings' => $settings,
        ]);
        $this->save($preset);

        return $preset;
    }
}
