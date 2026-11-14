<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Import;

use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use CsvIngest\Import\FieldSpec;
use CsvIngest\Import\ForeignKeyResolver;
use CsvIngest\Model\Behavior\IngestableBehavior;
use CsvIngest\Model\Table\IngestValueMapsTable;
use InvalidArgumentException;

class ForeignKeyResolverTest extends TestCase
{
    protected array $fixtures = [
        'plugin.CsvIngest.Companies',
        'plugin.CsvIngest.Contacts',
        'plugin.CsvIngest.IngestValueMaps',
    ];

    /**
     * @param array<string, string> $lookupFields
     */
    private function resolver(array $lookupFields = [], bool $withValueMaps = true): ForeignKeyResolver
    {
        $contacts = $this->fetchTable('Contacts');
        if ($lookupFields !== []) {
            $contacts->getBehavior('Ingestable')->setConfig('lookupFields', $lookupFields);
        }
        $maps = $withValueMaps ? $this->fetchTable('CsvIngest.IngestValueMaps') : null;
        assert($maps === null || $maps instanceof IngestValueMapsTable);

        return new ForeignKeyResolver($contacts, $maps);
    }

    private function companyField(): FieldSpec
    {
        $behavior = $this->fetchTable('Contacts')->getBehavior('Ingestable');
        assert($behavior instanceof IngestableBehavior);
        $field = $behavior->ingestField('company_id');
        assert($field !== null);

        return $field;
    }

    public function testResolvesByDisplayFieldOfAssociatedTable(): void
    {
        $this->assertSame(1, $this->resolver()->resolve($this->companyField(), 'Acme'));
        $this->assertSame(2, $this->resolver()->resolve($this->companyField(), ' Globex '), 'trimmed');
    }

    public function testResolvesByConfiguredLookupField(): void
    {
        $resolver = $this->resolver(['Companies' => 'tax_code']);

        $this->assertSame(2, $resolver->resolve($this->companyField(), 'B123'));
        $this->assertNull($resolver->resolve($this->companyField(), 'Globex'));
    }

    public function testExplicitValueMapWinsOverLookup(): void
    {
        $this->assertSame('2', $this->resolver()->resolve($this->companyField(), 'Glbx'));
    }

    public function testUnresolvedValuesAreCollectedOnceAndNotPersisted(): void
    {
        $resolver = $this->resolver();
        $field = $this->companyField();

        $this->assertNull($resolver->resolve($field, 'Initech'));
        $this->assertNull($resolver->resolve($field, 'Initech'));
        $this->assertNull($resolver->resolve($field, 'ACME Inc'), 'pending map does not resolve');

        $this->assertSame(['company_id' => ['Initech', 'ACME Inc']], $resolver->unresolved());
        $this->assertSame(0, $this->fetchTable('CsvIngest.IngestValueMaps')->find()->where(['source_value' => 'Initech'])->count());
    }

    public function testWorksWithoutValueMapsTable(): void
    {
        $resolver = $this->resolver(withValueMaps: false);

        $this->assertSame(1, $resolver->resolve($this->companyField(), 'Acme'));
        $this->assertNull($resolver->resolve($this->companyField(), 'Glbx'));
    }

    public function testCandidatesListAssociatedRecordsByLookupField(): void
    {
        $this->assertSame([1 => 'Acme', 2 => 'Globex'], $this->resolver()->candidates($this->companyField()));
        $this->assertSame(
            [1 => 'A111', 2 => 'B123'],
            $this->resolver(['Companies' => 'tax_code'])->candidates($this->companyField()),
        );
    }

    public function testRejectsFieldsThatAreNotForeignKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver()->resolve(new FieldSpec('name', 'Name', 'string'), 'x');
    }

    public function testLookupsAreCachedPerRun(): void
    {
        $resolver = $this->resolver();
        $field = $this->companyField();
        $resolver->resolve($field, 'Acme');

        $this->fetchTable('Companies')->updateAll(['name' => 'Renamed'], ['id' => 1]);

        $this->assertSame(1, $resolver->resolve($field, 'Acme'));
    }

    public function testTargetAliasUsedForValueMaps(): void
    {
        $this->assertInstanceOf(Table::class, $this->fetchTable('Contacts'));
        $this->assertSame('Contacts', $this->resolver()->targetAlias());
    }
}
