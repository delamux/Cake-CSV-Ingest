<?php
declare(strict_types=1);

namespace CsvIngest\Model\Table;

use Cake\Database\Exception\QueryException;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Translations of free CSV text into foreign-key values, per target table and field.
 *
 * A row with `mapped_value = NULL` is "pending": the value appeared in a CSV but
 * could not be resolved automatically and is waiting for a user decision.
 *
 * @method \CsvIngest\Model\Entity\IngestValueMap newEmptyEntity()
 * @method \CsvIngest\Model\Entity\IngestValueMap newEntity(array $data, array $options = [])
 * @method \CsvIngest\Model\Entity\IngestValueMap patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \CsvIngest\Model\Entity\IngestValueMap|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class IngestValueMapsTable extends Table
{
    /**
     * @param array<string, mixed> $config Config.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('csv_ingest_value_maps');
        $this->setDisplayField('source_value');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    /**
     * @param \Cake\Validation\Validator $validator Validator.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->scalar('target')
            ->maxLength('target', 100)
            ->requirePresence('target', 'create')
            ->notEmptyString('target')
            ->scalar('field')
            ->maxLength('field', 100)
            ->requirePresence('field', 'create')
            ->notEmptyString('field')
            ->scalar('source_value')
            ->maxLength('source_value', 255)
            ->requirePresence('source_value', 'create')
            ->notEmptyString('source_value')
            ->scalar('mapped_value')
            ->maxLength('mapped_value', 255)
            ->allowEmptyString('mapped_value');
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules Rules.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(
            ['target', 'field', 'source_value'],
            __d('csv_ingest', 'This value is already mapped for this field.'),
        ));

        return $rules;
    }

    /**
     * Value maps still waiting for a decision.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query.
     * @param string|null $target Target table alias.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findPending(SelectQuery $query, ?string $target = null): SelectQuery
    {
        $query->where(['OR' => [
            $this->aliasField('mapped_value') . ' IS' => null,
            $this->aliasField('mapped_value') => '',
        ]]);
        if ($target !== null) {
            $query->where([$this->aliasField('target') => $target]);
        }

        return $query->orderBy([$this->aliasField('field') => 'ASC', $this->aliasField('source_value') => 'ASC']);
    }

    /**
     * Admin listing filter.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query.
     * @param string|null $target Target alias.
     * @param string|null $field Field name.
     * @param string|null $search Text contained in source or mapped value.
     * @param bool|null $pending Only pending (true) or only resolved (false).
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findFiltered(
        SelectQuery $query,
        ?string $target = null,
        ?string $field = null,
        ?string $search = null,
        ?bool $pending = null,
    ): SelectQuery {
        if ($target !== null && $target !== '') {
            $query->where([$this->aliasField('target') => $target]);
        }
        if ($field !== null && $field !== '') {
            $query->where([$this->aliasField('field') => $field]);
        }
        if ($search !== null && $search !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $query->where(['OR' => [
                $this->aliasField('source_value') . ' LIKE' => $like,
                $this->aliasField('mapped_value') . ' LIKE' => $like,
            ]]);
        }
        if ($pending === true) {
            $query->where(['OR' => [
                $this->aliasField('mapped_value') . ' IS' => null,
                $this->aliasField('mapped_value') => '',
            ]]);
        } elseif ($pending === false) {
            $query->where([
                $this->aliasField('mapped_value') . ' IS NOT' => null,
                $this->aliasField('mapped_value') . ' !=' => '',
            ]);
        }

        return $query;
    }

    /**
     * Mapped value for a CSV text, or null when unknown or still pending.
     *
     * @param string $target Target alias.
     * @param string $field Field name.
     * @param string $sourceValue CSV text.
     * @return string|null
     */
    public function lookup(string $target, string $field, string $sourceValue): ?string
    {
        $row = $this->find()
            ->select(['mapped_value'])
            ->where([
                'target' => $target,
                'field' => $field,
                'source_value' => $sourceValue,
            ])
            ->disableHydration()
            ->first();
        $value = is_array($row) ? ($row['mapped_value'] ?? null) : null;

        return $value === null || $value === '' ? null : (string)$value;
    }

    /**
     * Record an unresolved CSV text so a user can map it later. Idempotent.
     *
     * @param string $target Target alias.
     * @param string $field Field name.
     * @param string $sourceValue CSV text.
     * @return void
     */
    public function registerPending(string $target, string $field, string $sourceValue): void
    {
        $sourceValue = mb_substr($sourceValue, 0, 255);
        if ($this->exists(['target' => $target, 'field' => $field, 'source_value' => $sourceValue])) {
            return;
        }
        $entity = $this->newEntity([
            'target' => $target,
            'field' => $field,
            'source_value' => $sourceValue,
            'mapped_value' => null,
        ]);
        try {
            $this->save($entity);
        } catch (QueryException) {
            // A concurrent import inserted the same value: the unique index did its job.
        }
    }

    /**
     * Save user decisions for pending values of one target.
     *
     * Ids belonging to another target are ignored (defense against tampered forms).
     *
     * @param string $target Target alias.
     * @param array<mixed> $assignments Value map id => mapped value.
     * @return array<int, array<string, mixed>> Id => validation errors, empty on success.
     */
    public function assign(string $target, array $assignments): array
    {
        $assignments = array_filter(
            $assignments,
            static fn(mixed $value, mixed $id): bool => is_numeric($id) && is_scalar($value) && $value !== '',
            ARRAY_FILTER_USE_BOTH,
        );
        if ($assignments === []) {
            return [];
        }

        $errors = [];
        $entities = $this->find()
            ->where([
                $this->aliasField('target') => $target,
                $this->aliasField('id') . ' IN' => array_map('intval', array_keys($assignments)),
            ])
            ->all();
        foreach ($entities as $entity) {
            $entity = $this->patchEntity($entity, ['mapped_value' => (string)$assignments[$entity->id]], [
                'fields' => ['mapped_value'],
            ]);
            if (!$this->save($entity)) {
                $errors[(int)$entity->id] = $entity->getErrors();
            }
        }

        return $errors;
    }

    /**
     * Distinct target aliases present in the table.
     *
     * @return array<string, string>
     */
    public function targetOptions(): array
    {
        /** @var array<string, string> */
        return $this->find('list', keyField: 'target', valueField: 'target')
            ->distinct(['target'])
            ->orderBy(['target' => 'ASC'])
            ->toArray();
    }
}
