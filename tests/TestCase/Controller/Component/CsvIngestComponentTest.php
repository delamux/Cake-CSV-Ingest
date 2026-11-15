<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Controller\Component;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Filesystem;
use Cake\Utility\Hash;
use CsvIngest\Controller\Component\CsvIngestComponent;
use CsvIngest\Import\UploadStore;
use CsvIngest\IngestSettings;
use Laminas\Diactoros\UploadedFile;
use TestApp\Controller\ContactsController;

/**
 * End-to-end wizard through a host controller using CsvIngestActionsTrait,
 * with CSRF middleware and FormProtection enabled (no unlocked actions).
 */
class CsvIngestComponentTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'plugin.CsvIngest.Companies',
        'plugin.CsvIngest.Contacts',
        'plugin.CsvIngest.IngestPresets',
        'plugin.CsvIngest.IngestValueMaps',
    ];

    private const SESSION_KEY = 'CsvIngest.uploads.Contacts';

    private const CSV = "Nombre;Correo;Empresa;Edad\nBea;bea@example.test;Globex;41\nCid;cid@example.test;Initech;\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->disableErrorHandlerMiddleware();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDir(IngestSettings::storagePath());
        Configure::write('CsvIngest.ownerResolver', null);
        parent::tearDown();
    }

    private function storedUpload(string $csv = self::CSV, ?int $presetId = null): string
    {
        $store = new UploadStore(IngestSettings::storagePath());
        $token = $store->storeContents($csv);
        $this->session([self::SESSION_KEY => [
            'token' => $token,
            'dialect' => ['delimiter' => 'semicolon', 'enclosure' => 'double', 'has_header_row' => true],
            'preset_id' => $presetId,
        ]]);

        return $token;
    }

    private function asOwner(string $id): void
    {
        Configure::write('CsvIngest.ownerResolver', static fn(ServerRequest $request): string => $id);
    }

    /**
     * @return mixed
     */
    private function sessionAfterRequest(string $key): mixed
    {
        $this->assertSessionHasKey($key);

        return Hash::get($_SESSION, $key);
    }

    public function testOwnerIdComesFromAuthenticationIdentityByDefault(): void
    {
        $identity = new class {
            public function getIdentifier(): int
            {
                return 7;
            }
        };
        $controller = new ContactsController((new ServerRequest())->withAttribute('identity', $identity));
        $component = $controller->components()->get('CsvIngest');
        $this->assertInstanceOf(CsvIngestComponent::class, $component);

        $this->assertSame(7, $component->ownerId());
        $this->assertSame('Contacts', $component->targetTable()->getAlias());

        $anonymous = new ContactsController(new ServerRequest());
        $this->assertNull($anonymous->components()->get('CsvIngest')->ownerId());
    }

    public function testUploadFormListsSharedAndOwnPresetsOnly(): void
    {
        $this->asOwner('7');
        $this->get('/contacts/csv-upload');

        $this->assertResponseOk();
        $this->assertResponseContains('type="file"');
        $this->assertResponseContains('Shared layout');
        $this->assertResponseContains('Mine');
        $this->assertResponseNotContains('Someone else');
        $this->assertResponseNotContains('Other target');
        $this->assertResponseContains('data-ci-upload');
        $this->assertSame(1, substr_count((string)$this->_response->getBody(), 'aria-current="step"'));
        $this->assertResponseContains('ci-steps__item--current" aria-current="step">');
    }

    public function testInvalidUploadRedisplaysTheFormWithErrors(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ci_');
        file_put_contents((string)$file, 'x');
        $this->post('/contacts/csv-upload', [
            'file' => new UploadedFile((string)$file, 1, UPLOAD_ERR_OK, 'book.xlsx'),
            'delimiter' => 'semicolon',
            'enclosure' => 'double',
            'has_header_row' => '1',
            'preset_id' => '',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Only .csv and .txt files are accepted.');
        $this->assertSessionNotHasKey(self::SESSION_KEY);
    }

    public function testValidUploadIsStoredAndRedirectsToMapping(): void
    {
        $file = (string)tempnam(sys_get_temp_dir(), 'ci_');
        file_put_contents($file, self::CSV);
        $this->post('/contacts/csv-upload', [
            'file' => new UploadedFile($file, strlen(self::CSV), UPLOAD_ERR_OK, 'people.csv'),
            'delimiter' => 'semicolon',
            'enclosure' => 'double',
            'has_header_row' => '1',
            'preset_id' => '1',
        ]);

        $this->assertRedirect('/contacts/csv-mapping');
        $state = $this->sessionAfterRequest(self::SESSION_KEY);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $state['token']);
        $this->assertSame(1, $state['preset_id']);
        $this->assertFileExists(IngestSettings::storagePath() . $state['token'] . '.csv');
    }

    public function testMappingWithoutUploadRedirectsBack(): void
    {
        $this->get('/contacts/csv-mapping');

        $this->assertRedirect('/contacts/csv-upload');
        $this->assertFlashElement('flash/error');
    }

    public function testMappingScreenGuessesColumnsAndShowsPreview(): void
    {
        $this->storedUpload("Name;Email;Company\nBea;bea@example.test;Globex\n");
        $this->get('/contacts/csv-mapping');

        $this->assertResponseOk();
        $this->assertResponseContains('bea@example.test', 'preview row');
        $this->assertMatchesRegularExpression(
            '/name="columns\[email\]"[^>]*>.*?<option value="1" selected="selected">/s',
            (string)$this->_response->getBody(),
        );
        $this->assertResponseContains('name="on_match"');
        $this->assertResponseContains('data-ci-mapper');
    }

    public function testWizardWorksOnAPrefixedHostController(): void
    {
        $this->storedUpload("Name;Email;Company\nBea;bea@example.test;Globex\n");

        $this->get('/admin/contacts/csv-mapping');

        $this->assertResponseOk();
        $this->assertResponseContains('/csv-ingest/presets/availability.json?target=Contacts', 'plugin URLs drop the host prefix');
        $this->assertResponseContains('href="/admin/contacts/csv-upload"', 'wizard links keep it');
    }

    public function testPresetDrivesInitialMappingAndPolicy(): void
    {
        $this->storedUpload(presetId: 1);
        $this->get('/contacts/csv-mapping');

        $body = (string)$this->_response->getBody();
        $this->assertMatchesRegularExpression('/name="columns\[name\]"[^>]*>.*?<option value="0" selected="selected">/s', $body);
        $this->assertMatchesRegularExpression('/name="columns\[email\]"[^>]*>.*?<option value="1" selected="selected">/s', $body);
        $this->assertMatchesRegularExpression('/<option value="patch" selected="selected">/', $body);
    }

    public function testRunningTheImportPersistsRowsAndShowsTheReport(): void
    {
        $this->storedUpload();
        $this->post('/contacts/csv-mapping', [
            'columns' => ['name' => '0', 'email' => '1', 'company_id' => '2', 'age' => '3'],
            'on_match' => 'skip',
            'dry_run' => '0',
            'preset_name' => '',
        ]);

        $this->assertResponseOk();
        $this->assertSame(1, $this->getTableLocator()->get('Contacts')->find()->where(['email' => 'bea@example.test'])->count());
        $this->assertResponseContains('data-ci-outcome="created"');
        $this->assertResponseContains('Initech', 'failure + pending value listed');
        $this->assertFlashElement('flash/warning');
    }

    public function testDryRunPersistsNothing(): void
    {
        $this->storedUpload();
        $this->post('/contacts/csv-mapping', [
            'columns' => ['name' => '0', 'email' => '1'],
            'on_match' => 'skip',
            'dry_run' => '1',
            'preset_name' => '',
        ]);

        $this->assertResponseOk();
        $this->assertSame(0, $this->getTableLocator()->get('Contacts')->find()->where(['email' => 'bea@example.test'])->count());
        $this->assertResponseContains('Dry run');
    }

    public function testIncompleteMappingIsReportedWithFieldLabels(): void
    {
        $this->storedUpload();
        $this->post('/contacts/csv-mapping', [
            'columns' => ['name' => '0'],
            'on_match' => 'skip',
            'dry_run' => '0',
            'preset_name' => '',
        ]);

        $this->assertResponseOk();
        $this->assertFlashMessage('Map these required fields before importing: Email');
    }

    public function testPresetIsSavedForTheCurrentOwner(): void
    {
        $this->asOwner('7');
        $this->storedUpload();
        $this->post('/contacts/csv-mapping', [
            'columns' => ['name' => '0', 'email' => '1'],
            'on_match' => 'patch',
            'dry_run' => '1',
            'preset_name' => 'Weekly export',
        ]);

        $preset = $this->getTableLocator()->get('CsvIngest.IngestPresets')->find()->where(['name' => 'Weekly export'])->firstOrFail();
        $this->assertSame('7', $preset->owner_id);
        $this->assertSame(['name' => 'Nombre', 'email' => 'Correo'], $preset->columnTitles());
        $this->assertSame('patch', $preset->settings['on_match']);
    }

    public function testPendingValueAssignmentsAreSavedBeforeTheRun(): void
    {
        $this->storedUpload("Nombre;Correo;Empresa\nBea;bea@example.test;ACME Inc\n");
        $this->post('/contacts/csv-mapping', [
            'columns' => ['name' => '0', 'email' => '1', 'company_id' => '2'],
            'pending' => ['1' => '1'],
            'on_match' => 'skip',
            'dry_run' => '0',
            'preset_name' => '',
        ]);

        $bea = $this->getTableLocator()->get('Contacts')->find()->where(['email' => 'bea@example.test'])->firstOrFail();
        $this->assertSame(1, $bea->company_id);
    }
}
