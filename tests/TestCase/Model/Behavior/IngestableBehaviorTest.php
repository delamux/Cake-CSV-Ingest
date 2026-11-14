<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Model\Behavior;

use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use CsvIngest\Exception\MisconfiguredTargetException;
use CsvIngest\Import\FieldSpec;
use CsvIngest\Model\Behavior\IngestableBehavior;

class IngestableBehaviorTest extends TestCase
{
    protected array $fixtures = [
        'plugin.CsvIngest.Companies',
        'plugin.CsvIngest.Contacts',
    ];

    /**
     * @param array<string, mixed> $config Behavior config.
     */
    private function contacts(array $config = []): Table
    {
        $table = $this->fetchTable('Contacts');
        if ($config !== []) {
            $table->removeBehavior('Ingestable');
            $table->addBehavior('CsvIngest.Ingestable', $config);
        }

        return $table;
    }

    private function behavior(Table $table): IngestableBehavior
    {
        $behavior = $table->getBehavior('Ingestable');
        $this->assertInstanceOf(IngestableBehavior::class, $behavior);

        return $behavior;
    }

    public function testIngestFieldsDescribeSchemaRequirementsAndAssociations(): void
    {
        $specs = $this->behavior($this->contacts())->ingestFields();
        $byName = [];
        foreach ($specs as $spec) {
            $byName[$spec->name] = $spec;
        }

        $this->assertSame(
            ['company_id', 'name', 'email', 'age', 'balance', 'is_active', 'born_on', 'notes'],
            array_keys($byName),
        );
        $this->assertTrue($byName['name']->required, 'requirePresence(create) makes it required');
        $this->assertTrue($byName['email']->required, 'match keys are always required');
        $this->assertTrue($byName['email']->isMatchKey);
        $this->assertFalse($byName['age']->required);
        $this->assertSame('Companies', $byName['company_id']->association);
        $this->assertSame('Company', $byName['company_id']->label, '_id suffix dropped from default label');
        $this->assertSame('Birth date', $byName['born_on']->label, 'explicit label wins');
        $this->assertSame('decimal', $byName['balance']->type);
        $this->assertNull($byName['name']->association);
    }

    public function testAlternativeValidationSetChangesRequiredFields(): void
    {
        $behavior = $this->behavior($this->contacts([
            'fields' => ['company_id', 'name'],
            'validation' => 'import',
        ]));

        $this->assertTrue($behavior->ingestField('company_id')?->required);
        $this->assertSame('import', $behavior->validationSet());
    }

    public function testFieldsMayBeAClosure(): void
    {
        $behavior = $this->behavior($this->contacts([
            'fields' => static fn(Table $table): array => ['name', $table->getAlias() === 'Contacts' ? 'email' : 'age'],
        ]));

        $this->assertSame(['name', 'email'], array_map(static fn(FieldSpec $s): string => $s->name, $behavior->ingestFields()));
    }

    public function testMatchOnDefaultsToDisplayFieldOnlyWhenImportable(): void
    {
        $this->assertSame(['name'], $this->behavior($this->contacts(['fields' => ['name', 'email']]))->matchFields());
        $this->assertSame([], $this->behavior($this->contacts(['fields' => ['email']]))->matchFields());
        $this->assertSame([], $this->behavior($this->contacts(['fields' => ['name'], 'matchOn' => false]))->matchFields());
    }

    public function testCompositeMatchKeyFindsExistingRecord(): void
    {
        $table = $this->contacts(['fields' => ['name', 'company_id'], 'matchOn' => ['name', 'company_id']]);

        $this->assertSame(1, $table->find('ingestMatch', values: ['name' => 'Ana', 'company_id' => 1])->count());
        $this->assertSame(0, $table->find('ingestMatch', values: ['name' => 'Ana', 'company_id' => 2])->count());
        $this->assertSame(0, $table->find('ingestMatch', values: ['name' => 'Ana'])->count(), 'missing key matches IS NULL');
    }

    public function testMatchingDisabledNeverMatches(): void
    {
        $table = $this->contacts(['fields' => ['name'], 'matchOn' => false]);

        $this->assertSame(0, $table->find('ingestMatch', values: ['name' => 'Ana'])->count());
    }

    public function testLookupFieldAccessor(): void
    {
        $behavior = $this->behavior($this->contacts(['fields' => ['company_id'], 'lookupFields' => ['Companies' => 'tax_code']]));

        $this->assertSame('tax_code', $behavior->lookupField('Companies'));
        $this->assertNull($behavior->lookupField('Users'));
    }

    public function testEmptyFieldsIsAConfigurationError(): void
    {
        $this->expectException(MisconfiguredTargetException::class);
        $this->behavior($this->contacts(['fields' => [], 'matchOn' => false]))->ingestFields();
    }

    public function testUnknownColumnIsAConfigurationError(): void
    {
        $this->expectException(MisconfiguredTargetException::class);
        $this->expectExceptionMessage('"nope"');
        $this->behavior($this->contacts(['fields' => ['name', 'nope']]))->ingestFields();
    }

    public function testMatchOnOutsideFieldsIsAConfigurationError(): void
    {
        $this->expectException(MisconfiguredTargetException::class);
        $this->behavior($this->contacts(['fields' => ['name'], 'matchOn' => 'email']))->matchFields();
    }
}
