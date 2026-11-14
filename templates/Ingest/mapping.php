<?php
/**
 * Wizard step 2: map columns, resolve pending values, run.
 *
 * Override in the host app: templates/plugin/CsvIngest/Ingest/mapping.php
 *
 * @var \Cake\View\View $this
 * @var list<string> $headers
 * @var list<list<string>> $preview
 * @var list<\CsvIngest\Import\FieldSpec> $fields
 * @var array<string, int> $columns
 * @var array<string, string> $onMatchOptions
 * @var string $onMatch
 * @var bool $dryRun
 * @var string $presetName
 * @var \CsvIngest\Import\IngestReport|null $report
 * @var list<\CsvIngest\Import\RowOutcome> $outcomes
 * @var list<\CsvIngest\Model\Entity\IngestValueMap> $pending
 * @var array<string, array<string|int, string>> $pendingOptions
 * @var string $targetAlias
 * @var bool $matchesExisting
 */

use function Cake\Core\h;
use function Cake\I18n\__d;

$this->Html->css('CsvIngest.csv-ingest', ['block' => true]);
$this->Html->script('CsvIngest.csv-ingest', ['block' => true, 'defer' => true]);

$headerOptions = [];
foreach ($headers as $index => $title) {
    $headerOptions[$index] = sprintf('%d. %s', $index + 1, $title !== '' ? $title : __d('csv_ingest', '(untitled)'));
}
$availabilityUrl = $this->Url->build([
    'plugin' => 'CsvIngest',
    'controller' => 'Presets',
    'action' => 'availability',
    '_ext' => 'json',
    '?' => ['target' => $targetAlias],
]);
?>
<section class="ci-wizard">
    <header class="ci-wizard__header">
        <h1 class="ci-wizard__title"><?= h(__d('csv_ingest', 'Import {0}', $targetAlias)) ?></h1>
        <?= $this->element('CsvIngest.steps', ['current' => 2]) ?>
    </header>

    <?php if ($report !== null) : ?>
        <?= $this->element('CsvIngest.report', compact('report', 'outcomes')) ?>
    <?php endif; ?>

    <?= $this->Form->create(null, ['class' => 'ci-form', 'data-ci-mapper' => '']) ?>

    <?php if ($pending !== []) : ?>
        <?= $this->element('CsvIngest.pending_values', compact('pending', 'pendingOptions', 'fields')) ?>
    <?php endif; ?>

    <fieldset class="ci-fieldset">
        <legend class="ci-fieldset__legend"><?= h(__d('csv_ingest', 'Columns')) ?></legend>
        <p class="ci-hint"><?= h(__d('csv_ingest', 'Choose which CSV column feeds each field. Fields marked * are required.')) ?></p>
        <div class="ci-table-wrap">
            <table class="ci-table ci-map" data-ci-samples="<?= h((string)json_encode($preview[0] ?? [])) ?>">
                <thead>
                    <tr>
                        <th scope="col"><?= h(__d('csv_ingest', 'Field')) ?></th>
                        <th scope="col"><?= h(__d('csv_ingest', 'CSV column')) ?></th>
                        <th scope="col"><?= h(__d('csv_ingest', 'Sample')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($fields as $field) :
                    $selected = $columns[$field->name] ?? null;
                    $selectId = 'ci-column-' . $field->name;
                    ?>
                    <tr class="ci-map__row<?= $selected === null ? ' ci-map__row--off' : '' ?>"
                        data-ci-field="<?= h($field->name) ?>"
                        data-ci-label="<?= h($field->label) ?>">
                        <th scope="row">
                            <label for="<?= h($selectId) ?>"><?= h($field->label) ?></label>
                            <?php if ($field->required) : ?>
                                <span class="ci-required" title="<?= h(__d('csv_ingest', 'Required')) ?>">*</span>
                            <?php endif; ?>
                            <?php if ($field->isMatchKey) : ?>
                                <span class="ci-badge"><?= h(__d('csv_ingest', 'match key')) ?></span>
                            <?php endif; ?>
                        </th>
                        <td>
                            <?= $this->Form->select('columns.' . $field->name, $headerOptions, [
                                'empty' => __d('csv_ingest', '— Do not import —'),
                                'value' => $selected ?? '',
                                'id' => $selectId,
                                'required' => $field->required,
                                'data-ci-column' => '',
                            ]) ?>
                        </td>
                        <td class="ci-map__sample" data-ci-sample=""><?= $selected !== null ? h($preview[0][$selected] ?? '') : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="ci-hint ci-hint--warning" data-ci-duplicates="" hidden></p>
    </fieldset>

    <fieldset class="ci-fieldset">
        <legend class="ci-fieldset__legend"><?= h(__d('csv_ingest', 'Options')) ?></legend>
        <?php if ($matchesExisting) : ?>
            <?= $this->Form->control('on_match', [
                'type' => 'select',
                'options' => $onMatchOptions,
                'value' => $onMatch,
                'label' => __d('csv_ingest', 'When a row matches an existing record'),
            ]) ?>
        <?php else : ?>
            <?= $this->Form->hidden('on_match', ['value' => 'skip']) ?>
            <p class="ci-hint"><?= h(__d('csv_ingest', 'Every row creates a new record.')) ?></p>
        <?php endif; ?>
        <?= $this->Form->control('dry_run', [
            'type' => 'checkbox',
            'checked' => $dryRun,
            'label' => __d('csv_ingest', 'Dry run: validate everything but save nothing'),
        ]) ?>
        <?= $this->Form->control('preset_name', [
            'type' => 'text',
            'value' => $presetName,
            'maxlength' => 100,
            'autocomplete' => 'off',
            'label' => __d('csv_ingest', 'Save this mapping as preset (optional)'),
            'data-ci-preset-name' => '',
            'data-ci-availability-url' => $availabilityUrl,
            'aria-describedby' => 'ci-preset-hint',
        ]) ?>
        <p class="ci-hint" id="ci-preset-hint" data-ci-preset-hint="" aria-live="polite"></p>
    </fieldset>

    <div class="ci-actions">
        <?= $this->Form->button(__d('csv_ingest', 'Import'), ['class' => 'ci-button ci-button--primary']) ?>
        <?= $this->Html->link(__d('csv_ingest', 'Upload another file'), ['action' => 'csvUpload'], ['class' => 'ci-button']) ?>
    </div>
    <?= $this->Form->end() ?>

    <?php if ($preview !== []) : ?>
        <details class="ci-preview" open>
            <summary><?= h(__d('csv_ingest', 'File preview (first {0} rows)', count($preview))) ?></summary>
            <div class="ci-table-wrap">
                <table class="ci-table">
                    <thead><tr><?php foreach ($headers as $title) : ?><th scope="col"><?= h($title) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($preview as $row) : ?>
                        <tr><?php foreach ($row as $value) : ?><td><?= h($value) ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    <?php endif; ?>
</section>
