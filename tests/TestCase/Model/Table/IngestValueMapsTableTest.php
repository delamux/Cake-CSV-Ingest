<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;
use CsvIngest\Model\Table\IngestValueMapsTable;

class IngestValueMapsTableTest extends TestCase
{
    protected array $fixtures = ['plugin.CsvIngest.IngestValueMaps'];

    private IngestValueMapsTable $maps;

    protected function setUp(): void
    {
        parent::setUp();
        $table = $this->fetchTable('CsvIngest.IngestValueMaps');
        assert($table instanceof IngestValueMapsTable);
        $this->maps = $table;
    }

    public function testLookupReturnsOnlyResolvedValues(): void
    {
        $this->assertSame('2', $this->maps->lookup('Contacts', 'company_id', 'Glbx'));
        $this->assertNull($this->maps->lookup('Contacts', 'company_id', 'ACME Inc'), 'pending');
        $this->assertNull($this->maps->lookup('Contacts', 'company_id', 'Unknown'));
        $this->assertNull($this->maps->lookup('Companies', 'company_id', 'Glbx'), 'scoped by target');
    }

    public function testRegisterPendingIsIdempotent(): void
    {
        $this->maps->registerPending('Contacts', 'company_id', 'Initech');
        $this->maps->registerPending('Contacts', 'company_id', 'Initech');
        $this->maps->registerPending('Contacts', 'company_id', 'Glbx');

        $this->assertSame(1, $this->maps->find()->where(['source_value' => 'Initech'])->count());
        $this->assertSame('2', $this->maps->lookup('Contacts', 'company_id', 'Glbx'), 'existing mapping untouched');
    }

    public function testFindPendingIsScopedAndOrdered(): void
    {
        $pending = $this->maps->find('pending', target: 'Contacts')->all()->extract('source_value')->toList();

        $this->assertSame(['ACME Inc'], $pending);
        $this->assertSame(2, $this->maps->find('pending')->count());
    }

    public function testAssignIgnoresOtherTargetsAndBlankValues(): void
    {
        $errors = $this->maps->assign('Contacts', [1 => '1', 3 => '99', 2 => '', 'x' => '5']);

        $this->assertSame([], $errors);
        $this->assertSame('1', $this->maps->lookup('Contacts', 'company_id', 'ACME Inc'));
        $this->assertNull($this->maps->get(3)->mapped_value, 'id 3 belongs to Companies');
        $this->assertSame('2', $this->maps->get(2)->mapped_value, 'blank assignment ignored');
    }

    public function testFindFiltered(): void
    {
        $this->assertSame(1, $this->maps->find('filtered', target: 'Contacts', pending: false)->count());
        $this->assertSame(2, $this->maps->find('filtered', target: 'Contacts')->count());
        $this->assertSame(1, $this->maps->find('filtered', search: 'acme')->count());
        $this->assertSame(1, $this->maps->find('filtered', field: 'country_id')->count());
        $this->assertSame(0, $this->maps->find('filtered', search: '%')->count(), 'LIKE wildcards escaped');
    }

    public function testTargetOptions(): void
    {
        $this->assertSame(['Companies' => 'Companies', 'Contacts' => 'Contacts'], $this->maps->targetOptions());
    }
}
