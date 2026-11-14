<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;
use CsvIngest\Import\Delimiter;
use CsvIngest\Import\OnMatch;
use CsvIngest\Model\Entity\IngestPreset;
use CsvIngest\Model\Table\IngestPresetsTable;

class IngestPresetsTableTest extends TestCase
{
    protected array $fixtures = ['plugin.CsvIngest.IngestPresets'];

    private IngestPresetsTable $presets;

    protected function setUp(): void
    {
        parent::setUp();
        $table = $this->fetchTable('CsvIngest.IngestPresets');
        assert($table instanceof IngestPresetsTable);
        $this->presets = $table;
    }

    public function testVisiblePresetsAreSharedPlusOwn(): void
    {
        $forOwner = $this->presets->find('visible', target: 'Contacts', ownerId: 7)->all()->extract('name')->toList();
        $anonymous = $this->presets->find('visible', target: 'Contacts')->all()->extract('name')->toList();

        $this->assertSame(['Mine', 'Shared layout'], $forOwner);
        $this->assertSame(['Shared layout'], $anonymous);
    }

    public function testSettingsAreDecodedIntoDomainObjects(): void
    {
        $preset = $this->presets->findOwned('Contacts', 'Shared layout', null);

        $this->assertInstanceOf(IngestPreset::class, $preset);
        $this->assertSame(['name' => 'Nombre', 'email' => 'Correo'], $preset->columnTitles());
        $this->assertSame(OnMatch::Patch, $preset->onMatch());
        $this->assertSame(Delimiter::Semicolon, $preset->dialect()->delimiter);
    }

    public function testRememberUpdatesExistingPresetOfSameOwner(): void
    {
        $preset = $this->presets->remember('Contacts', ' Mine ', ['columns' => ['email' => 'Mail'], 'on_match' => 'skip'], 7);

        $this->assertFalse($preset->hasErrors());
        $this->assertSame(4, $this->presets->find()->count(), 'updated, not inserted');
        $this->assertSame(['email' => 'Mail'], $this->presets->get($preset->id)->columnTitles());
    }

    public function testRememberCreatesPerOwnerAndRejectsSharedDuplicates(): void
    {
        $other = $this->presets->remember('Contacts', 'Mine', ['columns' => []], 9);
        $this->assertFalse($other->hasErrors(), 'same name allowed for another owner');

        $duplicate = $this->presets->newEntity([
            'target' => 'Contacts',
            'name' => 'Shared layout',
            'owner_id' => null,
            'settings' => ['columns' => []],
        ]);
        $this->assertFalse($this->presets->save($duplicate));
        $this->assertArrayHasKey('target', $duplicate->getErrors());
    }
}
