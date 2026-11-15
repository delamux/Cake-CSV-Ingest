<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Import;

use ArrayObject;
use Cake\Event\EventInterface;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use CsvIngest\Exception\IncompleteMappingException;
use CsvIngest\Exception\MisconfiguredTargetException;
use CsvIngest\Import\ColumnMap;
use CsvIngest\Import\CsvDialect;
use CsvIngest\Import\CsvSource;
use CsvIngest\Import\IngestEvents;
use CsvIngest\Import\IngestRunner;
use CsvIngest\Import\OnMatch;
use CsvIngest\Import\RowOutcome;
use CsvIngest\Import\ValueCaster;
use CsvIngest\Model\Table\IngestValueMapsTable;
use PHPUnit\Framework\Attributes\DataProvider;

class IngestRunnerTest extends TestCase
{
    protected array $fixtures = [
        'plugin.CsvIngest.Companies',
        'plugin.CsvIngest.Contacts',
        'plugin.CsvIngest.IngestValueMaps',
    ];

    /**
     * @var list<string>
     */
    private array $files = [];

    private Table $contacts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contacts = $this->fetchTable('Contacts');
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->files, 'is_file'));
        parent::tearDown();
    }

    private function source(string $csv): CsvSource
    {
        $path = (string)tempnam(sys_get_temp_dir(), 'ci_run_');
        file_put_contents($path, $csv);
        $this->files[] = $path;

        return CsvSource::open($path, new CsvDialect());
    }

    private function runner(int $maxRows = 0): IngestRunner
    {
        $maps = $this->fetchTable('CsvIngest.IngestValueMaps');
        assert($maps instanceof IngestValueMapsTable);

        return new IngestRunner(
            $this->contacts,
            $maps,
            new ValueCaster(['sí' => true, 'no' => false], ['Y-m-d', 'd/m/Y']),
            $maxRows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function contact(string $email): array
    {
        $row = $this->contacts->find()->where(['email' => $email])->disableHydration()->first();
        $this->assertIsArray($row, "contact {$email} exists");

        return $row;
    }

    private const HEADER = "Nombre;Correo;Empresa;Edad;Saldo;Activo;Nacimiento;Notas\n";

    private function fullMap(): ColumnMap
    {
        return new ColumnMap([
            'name' => 0, 'email' => 1, 'company_id' => 2, 'age' => 3,
            'balance' => 4, 'is_active' => 5, 'born_on' => 6, 'notes' => 7,
        ]);
    }

    public function testCreatesRecordsCastingValuesAndResolvingForeignKeys(): void
    {
        $csv = self::HEADER . "Bea;bea@example.test;Globex;41;1.234,50;Sí;12/03/1985;hello\n";

        $report = $this->runner()->run($this->source($csv), $this->fullMap());

        $this->assertSame(1, $report->count(RowOutcome::Created));
        $this->assertFalse($report->hasFailures());
        $bea = $this->contact('bea@example.test');
        $this->assertSame(2, $bea['company_id']);
        $this->assertSame(41, $bea['age']);
        $this->assertEqualsWithDelta(1234.5, (float)$bea['balance'], 0.001, 'scale is DB-specific');
        $this->assertTrue($bea['is_active']);
        $this->assertSame('1985-03-12', $bea['born_on']->format('Y-m-d'));
    }

    public function testExplicitValueMapResolvesForeignKey(): void
    {
        $csv = self::HEADER . "Bea;bea@example.test;Glbx;;;;;\n";

        $this->runner()->run($this->source($csv), $this->fullMap());

        $this->assertSame(2, $this->contact('bea@example.test')['company_id']);
    }

    public function testSkipLeavesExistingRecordUntouched(): void
    {
        $csv = self::HEADER . "Ana Changed;ana@example.test;Acme;99;;;;changed\n";

        $report = $this->runner()->run($this->source($csv), $this->fullMap(), OnMatch::Skip);

        $this->assertSame(1, $report->count(RowOutcome::Skipped));
        $this->assertSame('Ana', $this->contact('ana@example.test')['name']);
    }

    public function testReplaceOverwritesEveryMappedFieldIncludingBlanks(): void
    {
        $csv = self::HEADER . "Ana B;ana@example.test;Acme;;;;;\n";

        $report = $this->runner()->run($this->source($csv), $this->fullMap(), OnMatch::Replace);

        $this->assertSame(1, $report->count(RowOutcome::Replaced));
        $ana = $this->contact('ana@example.test');
        $this->assertSame('Ana B', $ana['name']);
        $this->assertNull($ana['age']);
        $this->assertSame('', $ana['notes']);
    }

    public function testAmbiguousMatchFailsTheRowAndChangesNoRecord(): void
    {
        $twin = $this->contacts->newEntity(['name' => 'Ana Twin', 'email' => 'ana@example.test']);
        $this->contacts->saveOrFail($twin);
        $csv = self::HEADER . "Ana Changed;ana@example.test;Acme;99;;;;changed\n"
            . "Bea;bea@example.test;Globex;;;;;\n";

        foreach ([OnMatch::Replace, OnMatch::Patch, OnMatch::Skip] as $onMatch) {
            $report = $this->runner()->run($this->source($csv), $this->fullMap(), $onMatch);

            $this->assertCount(1, $report->failures(), $onMatch->name);
            $failure = $report->failures()[0];
            $this->assertSame(2, $failure->line);
            $this->assertArrayHasKey('Correo', $failure->messages, 'reported under the match column');
            $this->assertStringContainsString('More than one', $failure->messages['Correo'][0]);
            $names = $this->contacts->find()->where(['email' => 'ana@example.test'])
                ->all()->extract('name')->toList();
            sort($names);
            $this->assertSame(['Ana', 'Ana Twin'], $names, 'neither twin changed');
        }
        $this->assertSame('Bea', $this->contact('bea@example.test')['name'], 'other rows still import');
    }

    public function testPatchKeepsExistingValuesForBlanksButAppliesZeroAndFalse(): void
    {
        $csv = self::HEADER . "Ana B;ana@example.test;;0;0;no;;\n";

        $report = $this->runner()->run($this->source($csv), $this->fullMap(), OnMatch::Patch);

        $this->assertSame(1, $report->count(RowOutcome::Patched));
        $ana = $this->contact('ana@example.test');
        $this->assertSame('Ana B', $ana['name']);
        $this->assertSame(1, $ana['company_id'], 'blank FK keeps current value');
        $this->assertSame(0, $ana['age']);
        $this->assertNotNull($ana['balance']);
        $this->assertEqualsWithDelta(0.0, (float)$ana['balance'], 0.001, 'zero applied, not dropped');
        $this->assertFalse($ana['is_active']);
        $this->assertSame('original', $ana['notes']);
    }

    public function testValidationFailuresAreReportedByLineAndHeaderAndOtherRowsContinue(): void
    {
        $csv = self::HEADER . ";carl@example.test;;;;;;\nDora;dora@example.test;;abc;;;;\nEve;eve@example.test;;;;;;\n";

        $report = $this->runner()->run($this->source($csv), $this->fullMap());

        $this->assertSame(1, $report->count(RowOutcome::Created));
        $this->assertSame(2, $report->count(RowOutcome::Failed));
        [$first, $second] = $report->failures();
        $this->assertSame(2, $first->line);
        $this->assertArrayHasKey('Nombre', $first->messages);
        $this->assertSame(3, $second->line);
        $this->assertArrayHasKey('Edad', $second->messages);
        $this->contact('eve@example.test');
    }

    public function testUnresolvedForeignKeyFailsRowAndIsRegisteredAsPending(): void
    {
        $csv = self::HEADER . "Bea;bea@example.test;Initech;;;;;\n";

        $report = $this->runner()->run($this->source($csv), $this->fullMap());

        $this->assertSame(1, $report->count(RowOutcome::Failed));
        $this->assertStringContainsString('Initech', $report->failures()[0]->messages['Empresa'][0]);
        $this->assertSame(['company_id' => ['Initech']], $report->unresolved());
        $this->assertSame(0, $this->contacts->find()->where(['email' => 'bea@example.test'])->count());
        $pending = $this->fetchTable('CsvIngest.IngestValueMaps')->find('pending', target: 'Contacts');
        $this->assertContains('Initech', $pending->all()->extract('source_value')->toList());
    }

    public function testDryRunRollsBackButStillRegistersPendingValues(): void
    {
        $before = $this->contacts->find()->count();
        $csv = self::HEADER . "Bea;bea@example.test;Acme;;;;;\nCid;cid@example.test;Initech;;;;;\n";

        $report = $this->runner()->run($this->source($csv), $this->fullMap(), dryRun: true);

        $this->assertTrue($report->dryRun);
        $this->assertSame(1, $report->count(RowOutcome::Created));
        $this->assertSame(1, $report->count(RowOutcome::Failed));
        $this->assertSame($before, $this->contacts->find()->count());
        $this->assertSame(
            1,
            $this->fetchTable('CsvIngest.IngestValueMaps')->find()->where(['source_value' => 'Initech'])->count(),
        );
    }

    public function testRowsMatchedWithinTheSameFileAreDetected(): void
    {
        $csv = self::HEADER . "Bea;bea@example.test;;;;;;one\nBea 2;bea@example.test;;;;;;two\n";

        $report = $this->runner()->run($this->source($csv), $this->fullMap(), OnMatch::Replace);

        $this->assertSame(1, $report->count(RowOutcome::Created));
        $this->assertSame(1, $report->count(RowOutcome::Replaced));
        $this->assertSame('two', $this->contact('bea@example.test')['notes']);
    }

    public function testMaxRowsTruncatesTheRun(): void
    {
        $csv = self::HEADER . "A;a@example.test;;;;;;\nB;b@example.test;;;;;;\nC;c@example.test;;;;;;\n";

        $report = $this->runner(maxRows: 2)->run($this->source($csv), $this->fullMap());

        $this->assertSame(2, $report->total());
        $this->assertTrue($report->isTruncated());
    }

    public function testMissingRequiredMappingIsRejectedBeforeAnyRowIsProcessed(): void
    {
        try {
            $this->runner()->run($this->source(self::HEADER), new ColumnMap(['notes' => 7]));
            $this->fail('Expected IncompleteMappingException');
        } catch (IncompleteMappingException $e) {
            $this->assertSame(['name', 'email'], $e->missingFields());
        }
    }

    public function testEventsLetHostsAlterDataAndReactToPersistedRows(): void
    {
        $seen = [];
        $manager = $this->contacts->getEventManager();
        $manager->on(IngestEvents::BEFORE_ROW_MARSHAL, function (EventInterface $event, ArrayObject $data): void {
            $data['name'] = strtoupper((string)$data['name']);
        });
        $manager->on(IngestEvents::AFTER_ROW_PERSIST, function (EventInterface $event) use (&$seen): void {
            $seen[] = [
                $event->getData('entity')->get('name'),
                $event->getData('sourceRow')['Notas'],
                $event->getData('outcome'),
                $event->getData('line'),
            ];
        });

        $this->runner()->run($this->source(self::HEADER . "bea;bea@example.test;;;;;;Almacen 1\n"), $this->fullMap());

        $this->assertSame([['BEA', 'Almacen 1', RowOutcome::Created, 2]], $seen);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function dryRunProvider(): array
    {
        return ['real run' => [false], 'dry run' => [true]];
    }

    #[DataProvider('dryRunProvider')]
    public function testSavesAreFlaggedAndRunLevelEventsWrapTheRun(bool $dryRun): void
    {
        $log = [];
        $manager = $this->contacts->getEventManager();
        $manager->on(IngestEvents::BEFORE_INGEST, function (EventInterface $event) use (&$log): void {
            $log[] = ['before', $event->getData('dryRun')];
        });
        $manager->on('Model.beforeSave', function (EventInterface $event, $entity, ArrayObject $options) use (&$log): void {
            $log[] = ['save', $options['csvIngest'] ?? null, $options['dryRun'] ?? null];
        });
        $manager->on(IngestEvents::AFTER_INGEST, function (EventInterface $event) use (&$log): void {
            $log[] = [
                'after',
                $event->getData('dryRun'),
                $event->getData('report')->count(RowOutcome::Created),
                $this->contacts->find()->where(['email' => 'bea@example.test'])->count(),
            ];
        });
        $csv = self::HEADER . "Bea;bea@example.test;;;;;;\nCid;cid@example.test;Initech;;;;;\n";

        $this->runner()->run($this->source($csv), $this->fullMap(), dryRun: $dryRun);

        $this->assertSame([
            ['before', $dryRun],
            ['save', true, $dryRun],
            ['after', $dryRun, 1, $dryRun ? 0 : 1],
        ], $log, 'afterIngest runs once, after a dry run has been rolled back');
    }

    public function testBeforeMarshalListenerCanSkipARow(): void
    {
        $this->contacts->getEventManager()->on(IngestEvents::BEFORE_ROW_MARSHAL, function (EventInterface $event): void {
            $event->stopPropagation();
        });

        $report = $this->runner()->run($this->source(self::HEADER . "Bea;bea@example.test;;;;;;\n"), $this->fullMap());

        $this->assertSame(1, $report->count(RowOutcome::Skipped));
    }

    public function testDatabaseRejectionsFailTheRowInsteadOfAbortingTheRun(): void
    {
        $this->contacts->getBehavior('Ingestable')->setConfig('validation', false);
        $this->contacts->getEventManager()->on(IngestEvents::BEFORE_ROW_MARSHAL, function (EventInterface $event, ArrayObject $data): void {
            if ($data['email'] === 'nameless@example.test') {
                $data['name'] = null;
            }
        });
        $csv = self::HEADER . "X;nameless@example.test;;;;;;\nBea;bea@example.test;;;;;;\n";

        $report = $this->runner()->run($this->source($csv), $this->fullMap());

        $this->assertSame(1, $report->count(RowOutcome::Failed), 'NOT NULL violation on contacts.name');
        $this->assertArrayHasKey('_database', $report->failures()[0]->messages);
        $this->assertSame(1, $report->count(RowOutcome::Created));
    }

    public function testTargetWithoutIngestableBehaviorIsRejected(): void
    {
        $this->expectException(MisconfiguredTargetException::class);
        new IngestRunner($this->fetchTable('Companies'));
    }
}
