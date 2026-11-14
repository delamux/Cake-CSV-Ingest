<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ValueMapsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'plugin.CsvIngest.Companies',
        'plugin.CsvIngest.Contacts',
        'plugin.CsvIngest.IngestValueMaps',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->disableErrorHandlerMiddleware();
    }

    public function testIndexListsAndFilters(): void
    {
        $this->get('/csv-ingest/value-maps');
        $this->assertResponseOk();
        $this->assertResponseContains('ACME Inc');
        $this->assertResponseContains('Glbx');
        $this->assertResponseContains('data-ci-value-map-filter');

        $this->get('/csv-ingest/value-maps?pending=1&target=Contacts');
        $this->assertResponseContains('ACME Inc');
        $this->assertResponseNotContains('Glbx');

        $this->get('/csv-ingest/value-maps?q=glb');
        $this->assertResponseContains('Glbx');
        $this->assertResponseNotContains('ACME Inc');
    }

    public function testEditOffersRecordsOfTheAssociatedTable(): void
    {
        $this->get('/csv-ingest/value-maps/edit/1');

        $this->assertResponseOk();
        $this->assertResponseContains('<option value="1">Acme</option>');
        $this->assertResponseContains('<option value="2">Globex</option>');
    }

    public function testEditSavesOnlyTheMappedValue(): void
    {
        $this->post('/csv-ingest/value-maps/edit/1', ['mapped_value' => '2', 'source_value' => 'hacked']);

        $this->assertRedirect('/csv-ingest/value-maps');
        $map = $this->getTableLocator()->get('CsvIngest.IngestValueMaps')->get(1);
        $this->assertSame('2', $map->mapped_value);
        $this->assertSame('ACME Inc', $map->source_value);
    }

    public function testEditRejectsTargetsThatAreNotIngestable(): void
    {
        $this->expectException(BadRequestException::class);
        $this->get('/csv-ingest/value-maps/edit/3');
    }

    public function testDeleteRequiresPostAndRemovesTheMap(): void
    {
        $this->post('/csv-ingest/value-maps/delete/2');

        $this->assertRedirect('/csv-ingest/value-maps');
        $this->assertFalse($this->getTableLocator()->get('CsvIngest.IngestValueMaps')->exists(['id' => 2]));

        $this->expectException(MethodNotAllowedException::class);
        $this->get('/csv-ingest/value-maps/delete/1');
    }
}
