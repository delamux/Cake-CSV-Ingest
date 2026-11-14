<?php
declare(strict_types=1);

namespace CsvIngest\Controller\Component;

use Cake\Controller\Component;
use Cake\Http\Response;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use CsvIngest\Exception\IncompleteMappingException;
use CsvIngest\Exception\MisconfiguredTargetException;
use CsvIngest\Exception\UnreadableSourceException;
use CsvIngest\Form\UploadForm;
use CsvIngest\Import\ColumnMap;
use CsvIngest\Import\CsvDialect;
use CsvIngest\Import\CsvSource;
use CsvIngest\Import\Delimiter;
use CsvIngest\Import\Enclosure;
use CsvIngest\Import\FieldSpec;
use CsvIngest\Import\ForeignKeyResolver;
use CsvIngest\Import\IngestReport;
use CsvIngest\Import\IngestRunner;
use CsvIngest\Import\OnMatch;
use CsvIngest\Import\RowOutcome;
use CsvIngest\Import\UploadStore;
use CsvIngest\IngestSettings;
use CsvIngest\Model\Behavior\IngestableBehavior;
use CsvIngest\Model\Entity\IngestPreset;
use CsvIngest\Model\Table\IngestPresetsTable;
use CsvIngest\Model\Table\IngestValueMapsTable;
use CsvIngest\OwnerIdentity;
use Psr\Http\Message\UploadedFileInterface;
use function Cake\I18n\__d;

/**
 * Drives the two-step import wizard for the controller's target table.
 *
 * Config:
 * - `target` (string|null): table alias to import into; defaults to the controller's default table.
 * - `uploadAction` / `mappingAction` (string): action names used for redirects.
 *
 * @property \Cake\Controller\Component\FlashComponent $Flash
 */
class CsvIngestComponent extends Component
{
    use LocatorAwareTrait;

    /**
     * @var array<int|string, array<string, mixed>|string>
     */
    protected array $components = ['Flash'];

    /**
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'target' => null,
        'uploadAction' => 'csvUpload',
        'mappingAction' => 'csvMapping',
    ];

    /**
     * Step 1: choose the file, dialect and optional preset.
     *
     * @return \Cake\Http\Response|null
     */
    public function upload(): ?Response
    {
        $controller = $this->getController();
        $request = $controller->getRequest();
        $target = $this->targetTable();
        $form = new UploadForm(IngestSettings::maxFileSize());
        $presets = $this->presets()->find('visible', target: $target->getAlias(), ownerId: $this->ownerId())
            ->find('list')
            ->toArray();

        if ($request->is('post')) {
            $data = (array)$request->getData();
            if ($form->validate($data) && $data['file'] instanceof UploadedFileInterface) {
                $this->forgetUpload();
                $presetId = (int)($data['preset_id'] ?? 0);
                $request->getSession()->write($this->sessionKey(), [
                    'token' => $this->uploadStore()->store($data['file']),
                    'dialect' => CsvDialect::fromArray($data)->toArray(),
                    'preset_id' => isset($presets[$presetId]) ? $presetId : null,
                ]);

                return $controller->redirect(['action' => $this->getConfig('mappingAction')]);
            }
            $this->Flash->error(__d('csv_ingest', 'The file could not be uploaded. Please check the errors below.'));
        }

        $controller->set([
            'uploadForm' => $form,
            'presets' => $presets,
            'delimiters' => Delimiter::options(),
            'enclosures' => Enclosure::options(),
            'maxFileSize' => $form->maxFileSize(),
            'targetAlias' => $target->getAlias(),
        ]);
        $this->useTemplate('upload');

        return null;
    }

    /**
     * Step 2: map columns, resolve pending values and run the import.
     *
     * @return \Cake\Http\Response|null
     */
    public function mapping(): ?Response
    {
        $controller = $this->getController();
        $request = $controller->getRequest();
        $state = $request->getSession()->read($this->sessionKey());
        $uploadUrl = ['action' => $this->getConfig('uploadAction')];
        if (!is_array($state) || !is_string($state['token'] ?? null)) {
            $this->Flash->error(__d('csv_ingest', 'Upload a CSV file first.'));

            return $controller->redirect($uploadUrl);
        }

        $dialect = CsvDialect::fromArray((array)($state['dialect'] ?? []));
        try {
            $source = CsvSource::open($this->uploadStore()->path($state['token']), $dialect);
        } catch (UnreadableSourceException $e) {
            $this->forgetUpload();
            $this->Flash->error($e->getMessage());

            return $controller->redirect($uploadUrl);
        }

        $target = $this->targetTable();
        $behavior = $this->behavior($target);
        $fields = $behavior->ingestFields();
        $fieldNames = array_map(static fn(FieldSpec $f): string => $f->name, $fields);
        $preset = $this->loadPreset($state['preset_id'] ?? null);

        $map = $preset !== null
            ? ColumnMap::fromHeaderTitles($preset->columnTitles(), $source->headers(), $fieldNames)
            : ColumnMap::guess($fields, $source->headers());
        $onMatch = $preset?->onMatch() ?? OnMatch::Skip;
        $dryRun = false;
        $presetName = $preset !== null ? $preset->name : '';
        $report = null;

        if ($request->is(['post', 'put'])) {
            $map = ColumnMap::fromInput((array)$request->getData('columns'), $fieldNames, $source->columnCount());
            $onMatch = OnMatch::tryFrom((string)$request->getData('on_match')) ?? OnMatch::Skip;
            $dryRun = filter_var($request->getData('dry_run'), FILTER_VALIDATE_BOOL);
            $presetName = trim((string)$request->getData('preset_name'));

            $this->assignPendingValues($target, (array)$request->getData('pending'));
            if ($presetName !== '') {
                $this->rememberPreset($target, $presetName, $map, $onMatch, $source);
            }
            $report = $this->runImport($target, $source, $map, $onMatch, $dryRun, $fields);
        }

        $resolver = new ForeignKeyResolver($target, $this->valueMaps());
        $pending = $this->valueMaps()->find('pending', target: $target->getAlias())->all()->toList();
        $pendingOptions = [];
        foreach ($fields as $field) {
            if ($field->isForeignKey()) {
                $pendingOptions[$field->name] = $resolver->candidates($field);
            }
        }

        $controller->set([
            'headers' => $source->headers(),
            'preview' => $source->preview(IngestSettings::previewRows()),
            'fields' => $fields,
            'columns' => $map->columns,
            'onMatchOptions' => OnMatch::options(),
            'onMatch' => $onMatch->value,
            'dryRun' => $dryRun,
            'presetName' => $presetName,
            'report' => $report,
            'outcomes' => RowOutcome::cases(),
            'pending' => $pending,
            'pendingOptions' => $pendingOptions,
            'targetAlias' => $target->getAlias(),
            'matchesExisting' => $behavior->matchFields() !== [],
        ]);
        $this->useTemplate('mapping');

        return null;
    }

    /**
     * Table receiving the rows.
     *
     * @return \Cake\ORM\Table
     */
    public function targetTable(): Table
    {
        $alias = $this->getConfig('target');

        return is_string($alias) ? $this->fetchTable($alias) : $this->getController()->fetchTable();
    }

    /**
     * Current preset owner id (null = anonymous/shared).
     *
     * @return string|int|null
     */
    public function ownerId(): string|int|null
    {
        return OwnerIdentity::fromRequest($this->getController()->getRequest());
    }

    /**
     * @param \Cake\ORM\Table $target Target.
     * @param \CsvIngest\Import\CsvSource $source Source.
     * @param \CsvIngest\Import\ColumnMap $map Map.
     * @param \CsvIngest\Import\OnMatch $onMatch Policy.
     * @param bool $dryRun Dry run.
     * @param list<\CsvIngest\Import\FieldSpec> $fields Fields.
     * @return \CsvIngest\Import\IngestReport|null
     */
    private function runImport(
        Table $target,
        CsvSource $source,
        ColumnMap $map,
        OnMatch $onMatch,
        bool $dryRun,
        array $fields,
    ): ?IngestReport {
        if (IngestSettings::timeLimit() > 0) {
            set_time_limit(IngestSettings::timeLimit());
        }
        $runner = new IngestRunner($target, $this->valueMaps(), null, IngestSettings::maxRows());
        try {
            $report = $runner->run($source, $map, $onMatch, $dryRun);
        } catch (IncompleteMappingException $e) {
            $labels = [];
            foreach ($fields as $field) {
                if (in_array($field->name, $e->missingFields(), true)) {
                    $labels[] = $field->label;
                }
            }
            $this->Flash->error(__d(
                'csv_ingest',
                'Map these required fields before importing: {0}',
                implode(', ', $labels),
            ));

            return null;
        }

        $summary = __d(
            'csv_ingest',
            '{0} rows processed: {1} created, {2} replaced, {3} patched, {4} skipped, {5} failed.',
            $report->total(),
            $report->count(RowOutcome::Created),
            $report->count(RowOutcome::Replaced),
            $report->count(RowOutcome::Patched),
            $report->count(RowOutcome::Skipped),
            $report->count(RowOutcome::Failed),
        );
        if ($dryRun) {
            $summary = __d('csv_ingest', 'Dry run, nothing was saved.') . ' ' . $summary;
        }
        if ($report->hasFailures() || $report->isTruncated()) {
            $this->Flash->warning($summary);
        } else {
            $this->Flash->success($summary);
        }

        return $report;
    }

    /**
     * @param \Cake\ORM\Table $target Target.
     * @param array<mixed> $assignments Value map id => key.
     * @return void
     */
    private function assignPendingValues(Table $target, array $assignments): void
    {
        if ($assignments === []) {
            return;
        }
        $errors = $this->valueMaps()->assign($target->getAlias(), $assignments);
        if ($errors !== []) {
            $this->Flash->error(__d('csv_ingest', 'Some pending values could not be saved.'));
        }
    }

    /**
     * @param \Cake\ORM\Table $target Target.
     * @param string $name Preset name.
     * @param \CsvIngest\Import\ColumnMap $map Map.
     * @param \CsvIngest\Import\OnMatch $onMatch Policy.
     * @param \CsvIngest\Import\CsvSource $source Source.
     * @return void
     */
    private function rememberPreset(
        Table $target,
        string $name,
        ColumnMap $map,
        OnMatch $onMatch,
        CsvSource $source,
    ): void {
        $preset = $this->presets()->remember($target->getAlias(), $name, [
            'columns' => $map->toHeaderTitles($source->headers()),
            'on_match' => $onMatch->value,
            'dialect' => $source->dialect->toArray(),
        ], $this->ownerId());

        if ($preset->hasErrors()) {
            $this->Flash->error(__d('csv_ingest', 'The preset could not be saved.'));
        }
    }

    /**
     * @param mixed $id Preset id from session.
     * @return \CsvIngest\Model\Entity\IngestPreset|null
     */
    private function loadPreset(mixed $id): ?IngestPreset
    {
        if (!is_int($id)) {
            return null;
        }

        /** @var \CsvIngest\Model\Entity\IngestPreset|null */
        return $this->presets()
            ->find('visible', target: $this->targetTable()->getAlias(), ownerId: $this->ownerId())
            ->where([$this->presets()->aliasField('id') => $id])
            ->first();
    }

    /**
     * @param \Cake\ORM\Table $target Target.
     * @return \CsvIngest\Model\Behavior\IngestableBehavior
     */
    private function behavior(Table $target): IngestableBehavior
    {
        $behavior = $target->hasBehavior('Ingestable') ? $target->getBehavior('Ingestable') : null;
        if (!$behavior instanceof IngestableBehavior) {
            throw new MisconfiguredTargetException(sprintf(
                'Table "%s" must use the CsvIngest.Ingestable behavior.',
                $target->getAlias(),
            ));
        }

        return $behavior;
    }

    /**
     * @param string $step upload|mapping
     * @return void
     */
    private function useTemplate(string $step): void
    {
        $this->getController()->viewBuilder()
            ->setPlugin('CsvIngest')
            ->setTemplatePath('Ingest')
            ->setTemplate($step);
    }

    /**
     * @return void
     */
    private function forgetUpload(): void
    {
        $session = $this->getController()->getRequest()->getSession();
        $token = $session->read($this->sessionKey() . '.token');
        if (is_string($token)) {
            $this->uploadStore()->discard($token);
        }
        $session->delete($this->sessionKey());
    }

    /**
     * @return string
     */
    private function sessionKey(): string
    {
        return 'CsvIngest.uploads.' . $this->targetTable()->getAlias();
    }

    /**
     * @return \CsvIngest\Import\UploadStore
     */
    private function uploadStore(): UploadStore
    {
        return new UploadStore(IngestSettings::storagePath(), IngestSettings::encodings(), IngestSettings::uploadTtl());
    }

    /**
     * @return \CsvIngest\Model\Table\IngestPresetsTable
     */
    private function presets(): IngestPresetsTable
    {
        $table = $this->fetchTable('CsvIngest.IngestPresets');
        assert($table instanceof IngestPresetsTable);

        return $table;
    }

    /**
     * @return \CsvIngest\Model\Table\IngestValueMapsTable
     */
    private function valueMaps(): IngestValueMapsTable
    {
        $table = $this->fetchTable('CsvIngest.IngestValueMaps');
        assert($table instanceof IngestValueMapsTable);

        return $table;
    }
}
