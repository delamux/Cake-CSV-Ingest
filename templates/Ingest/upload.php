<?php
/**
 * Wizard step 1: upload.
 *
 * Override in the host app: templates/plugin/CsvIngest/Ingest/upload.php
 *
 * @var \Cake\View\View $this
 * @var \CsvIngest\Form\UploadForm $uploadForm
 * @var array<int, string> $presets
 * @var array<string, string> $delimiters
 * @var array<string, string> $enclosures
 * @var int $maxFileSize
 * @var string $targetAlias
 */

use function Cake\Core\h;
use function Cake\I18n\__d;

$this->Html->css('CsvIngest.csv-ingest', ['block' => true]);
$this->Html->script('CsvIngest.csv-ingest', ['block' => true, 'defer' => true]);
?>
<section class="ci-wizard" data-ci-upload="">
    <header class="ci-wizard__header">
        <h1 class="ci-wizard__title"><?= h(__d('csv_ingest', 'Import {0}', $targetAlias)) ?></h1>
        <?= $this->element('CsvIngest.steps', ['current' => 1]) ?>
    </header>

    <?= $this->Form->create($uploadForm, ['type' => 'file', 'class' => 'ci-form']) ?>
    <div class="ci-form__group">
        <?= $this->Form->control('file', [
            'type' => 'file',
            'label' => __d('csv_ingest', 'CSV file'),
            'accept' => '.csv,.txt,text/csv,text/plain',
            'required' => true,
            'data-ci-file' => '',
            'data-ci-max-size' => (string)$maxFileSize,
            'data-ci-msg-too-large' => __d('csv_ingest', 'The file is larger than the {0} limit.'),
            'aria-describedby' => 'ci-file-hint',
        ]) ?>
        <p class="ci-hint" id="ci-file-hint" data-ci-file-info="" aria-live="polite">
            <?= h(__d(
                'csv_ingest',
                'Accepted: .csv or .txt, up to {0} MB. Any common encoding; it is converted to UTF-8.',
                round($maxFileSize / 1048576, 1),
            )) ?>
        </p>
    </div>

    <fieldset class="ci-fieldset">
        <legend class="ci-fieldset__legend"><?= h(__d('csv_ingest', 'File format')) ?></legend>
        <?= $this->Form->control('delimiter', [
            'type' => 'select',
            'options' => $delimiters,
            'default' => 'semicolon',
            'label' => __d('csv_ingest', 'Field separator'),
            'data-ci-delimiter' => '',
        ]) ?>
        <?= $this->Form->control('enclosure', [
            'type' => 'select',
            'options' => $enclosures,
            'default' => 'double',
            'label' => __d('csv_ingest', 'Text quoted with'),
        ]) ?>
        <?= $this->Form->control('has_header_row', [
            'type' => 'checkbox',
            'default' => true,
            'label' => __d('csv_ingest', 'The first row contains column titles'),
        ]) ?>
    </fieldset>

    <?php if ($presets !== []) : ?>
        <?= $this->Form->control('preset_id', [
            'type' => 'select',
            'options' => $presets,
            'empty' => __d('csv_ingest', '— No preset —'),
            'label' => __d('csv_ingest', 'Saved preset'),
        ]) ?>
    <?php else : ?>
        <?= $this->Form->hidden('preset_id', ['value' => '']) ?>
    <?php endif; ?>

    <div class="ci-actions">
        <?= $this->Form->button(__d('csv_ingest', 'Continue'), ['class' => 'ci-button ci-button--primary']) ?>
    </div>
    <?= $this->Form->end() ?>
</section>
