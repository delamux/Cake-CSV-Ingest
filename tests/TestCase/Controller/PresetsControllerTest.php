<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\ServerRequest;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class PresetsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['plugin.CsvIngest.IngestPresets'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->disableErrorHandlerMiddleware();
        Configure::write('CsvIngest.ownerResolver', static fn(ServerRequest $request): string => '7');
    }

    protected function tearDown(): void
    {
        Configure::write('CsvIngest.ownerResolver', null);
        parent::tearDown();
    }

    public function testIndexListsPresetsVisibleToTheCurrentOwner(): void
    {
        $this->get('/csv-ingest/presets');

        $this->assertResponseOk();
        $this->assertResponseContains('Shared layout');
        $this->assertResponseContains('Mine');
        $this->assertResponseContains('Other target');
        $this->assertResponseNotContains('Someone else');
    }

    public function testIndexCanBeFilteredByTarget(): void
    {
        $this->get('/csv-ingest/presets?target=Companies');

        $this->assertResponseOk();
        $this->assertResponseContains('Other target');
        $this->assertResponseNotContains('Shared layout');
    }

    public function testDeleteRequiresPost(): void
    {
        $this->expectException(MethodNotAllowedException::class);
        $this->get('/csv-ingest/presets/delete/2');
    }

    public function testDeleteOwnPreset(): void
    {
        $this->post('/csv-ingest/presets/delete/2');

        $this->assertRedirect('/csv-ingest/presets');
        $this->assertFlashElement('flash/success');
        $this->assertFalse($this->getTableLocator()->get('CsvIngest.IngestPresets')->exists(['id' => 2]));
    }

    public function testCannotDeleteSomeoneElsesPreset(): void
    {
        $this->expectException(RecordNotFoundException::class);
        $this->post('/csv-ingest/presets/delete/3');
    }

    public function testAvailabilityEndpoint(): void
    {
        $this->get('/csv-ingest/presets/availability.json?target=Contacts&name=Mine');
        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $this->assertSame(['available' => false], json_decode((string)$this->_response->getBody(), true));

        $this->get('/csv-ingest/presets/availability.json?target=Contacts&name=Someone%20else');
        $this->assertSame(['available' => true], json_decode((string)$this->_response->getBody(), true));

        $this->get('/csv-ingest/presets/availability.json?target=Contacts&name=');
        $this->assertSame(['available' => true], json_decode((string)$this->_response->getBody(), true));
    }
}
