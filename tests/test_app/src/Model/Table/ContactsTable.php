<?php
declare(strict_types=1);

namespace TestApp\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Test import target. Mirrors a typical host table (BelongsTo + required field).
 *
 * @property \TestApp\Model\Table\CompaniesTable&\Cake\ORM\Association\BelongsTo $Companies
 */
class ContactsTable extends Table
{
    /**
     * @param array<string, mixed> $config Config.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('contacts');
        $this->setDisplayField('name');
        $this->belongsTo('Companies', ['foreignKey' => 'company_id']);
        $this->addBehavior('CsvIngest.Ingestable', [
            'fields' => ['company_id', 'name', 'email', 'age', 'balance', 'is_active', 'born_on', 'notes'],
            'matchOn' => 'email',
            'labels' => ['born_on' => 'Birth date'],
        ]);
    }

    /**
     * @param \Cake\Validation\Validator $validator Validator.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->email('email')
            ->allowEmptyString('email')
            ->integer('age')
            ->allowEmptyString('age')
            ->decimal('balance')
            ->allowEmptyString('balance')
            ->boolean('is_active')
            ->allowEmptyString('is_active')
            ->date('born_on')
            ->allowEmptyDate('born_on');
    }

    /**
     * Stricter set used to test the `validation` option.
     *
     * @param \Cake\Validation\Validator $validator Validator.
     * @return \Cake\Validation\Validator
     */
    public function validationImport(Validator $validator): Validator
    {
        return $this->validationDefault($validator)
            ->requirePresence('company_id', 'create')
            ->notEmptyString('company_id');
    }
}
