<?php
/**
 * @var \Cake\View\View $this
 * @var \CsvIngest\Model\Entity\IngestValueMap $valueMap
 * @var \CsvIngest\Import\FieldSpec $field
 * @var array<string|int, string> $options
 */

use function Cake\Core\h;
use function Cake\I18n\__d;

$this->Html->css('CsvIngest.csv-ingest', ['block' => true]);
?>
<section class="ci-admin">
    <header class="ci-admin__header">
        <h1><?= h(__d('csv_ingest', 'Map a CSV value')) ?></h1>
    </header>

    <dl class="ci-summary">
        <dt><?= h(__d('csv_ingest', 'Table')) ?></dt><dd><?= h($valueMap->target) ?></dd>
        <dt><?= h(__d('csv_ingest', 'Field')) ?></dt><dd><?= h($field->label) ?></dd>
        <dt><?= h(__d('csv_ingest', 'Value in CSV')) ?></dt><dd><?= h($valueMap->source_value) ?></dd>
    </dl>

    <?= $this->Form->create($valueMap, ['class' => 'ci-form']) ?>
    <?= $this->Form->control('mapped_value', [
        'type' => 'select',
        'options' => $options,
        'empty' => __d('csv_ingest', '— Pending —'),
        'label' => __d('csv_ingest', 'Means'),
    ]) ?>
    <div class="ci-actions">
        <?= $this->Form->button(__d('csv_ingest', 'Save'), ['class' => 'ci-button ci-button--primary']) ?>
        <?= $this->Html->link(__d('csv_ingest', 'Cancel'), ['action' => 'index'], ['class' => 'ci-button']) ?>
    </div>
    <?= $this->Form->end() ?>
</section>
