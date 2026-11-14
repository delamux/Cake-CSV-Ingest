<?php
/**
 * @var \Cake\View\View $this
 * @var iterable<\CsvIngest\Model\Entity\IngestValueMap> $valueMaps
 * @var array{target: string|null, field: string|null, q: string|null, pending: string|null} $filters
 * @var array<string, string> $targets
 * @var array<string, list<string>> $fieldsByTarget
 */

use function Cake\Core\h;
use function Cake\I18n\__d;

$this->Html->css('CsvIngest.csv-ingest', ['block' => true]);
$this->Html->script('CsvIngest.csv-ingest', ['block' => true, 'defer' => true]);

$fieldOptions = [];
foreach ($fieldsByTarget as $targetFields) {
    foreach ($targetFields as $field) {
        $fieldOptions[$field] = $field;
    }
}
ksort($fieldOptions);
?>
<section class="ci-admin">
    <header class="ci-admin__header">
        <h1><?= h(__d('csv_ingest', 'Value maps')) ?></h1>
        <p class="ci-hint"><?= h(__d('csv_ingest', 'How CSV texts translate into related records for each importable table.')) ?></p>
    </header>

    <?= $this->Form->create(null, [
        'type' => 'get',
        'valueSources' => ['query'],
        'class' => 'ci-filters',
        'data-ci-value-map-filter' => '',
        'data-ci-fields' => (string)json_encode($fieldsByTarget),
    ]) ?>
    <?= $this->Form->control('target', [
        'type' => 'select',
        'options' => $targets,
        'empty' => __d('csv_ingest', 'All tables'),
        'label' => __d('csv_ingest', 'Table'),
        'data-ci-filter-target' => '',
    ]) ?>
    <?= $this->Form->control('field', [
        'type' => 'select',
        'options' => $fieldOptions,
        'empty' => __d('csv_ingest', 'All fields'),
        'label' => __d('csv_ingest', 'Field'),
        'data-ci-filter-field' => '',
    ]) ?>
    <?= $this->Form->control('q', ['type' => 'search', 'label' => __d('csv_ingest', 'Text')]) ?>
    <?= $this->Form->control('pending', [
        'type' => 'select',
        'options' => ['1' => __d('csv_ingest', 'Pending'), '0' => __d('csv_ingest', 'Assigned')],
        'empty' => __d('csv_ingest', 'Any status'),
        'label' => __d('csv_ingest', 'Status'),
    ]) ?>
    <?= $this->Form->button(__d('csv_ingest', 'Filter'), ['class' => 'ci-button']) ?>
    <?= $this->Html->link(__d('csv_ingest', 'Reset'), ['action' => 'index'], ['class' => 'ci-button']) ?>
    <?= $this->Form->end() ?>

    <div class="ci-table-wrap">
        <table class="ci-table">
            <thead>
                <tr>
                    <th scope="col"><?= $this->Paginator->sort('target', __d('csv_ingest', 'Table')) ?></th>
                    <th scope="col"><?= $this->Paginator->sort('field', __d('csv_ingest', 'Field')) ?></th>
                    <th scope="col"><?= $this->Paginator->sort('source_value', __d('csv_ingest', 'Value in CSV')) ?></th>
                    <th scope="col"><?= $this->Paginator->sort('mapped_value', __d('csv_ingest', 'Mapped to')) ?></th>
                    <th scope="col"><span class="ci-visually-hidden"><?= h(__d('csv_ingest', 'Actions')) ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($valueMaps as $valueMap) : ?>
                <tr>
                    <td><?= h($valueMap->target) ?></td>
                    <td><?= h($valueMap->field) ?></td>
                    <td><?= h($valueMap->source_value) ?></td>
                    <td>
                        <?php if ($valueMap->isPending()) : ?>
                            <span class="ci-badge ci-badge--warning"><?= h(__d('csv_ingest', 'Pending')) ?></span>
                        <?php else : ?>
                            <?= h((string)$valueMap->mapped_value) ?>
                        <?php endif; ?>
                    </td>
                    <td class="ci-table__actions">
                        <?= $this->Html->link(__d('csv_ingest', 'Edit'), ['action' => 'edit', $valueMap->id], ['class' => 'ci-button']) ?>
                        <?= $this->Form->postLink(
                            __d('csv_ingest', 'Delete'),
                            ['action' => 'delete', $valueMap->id],
                            [
                                'confirm' => __d('csv_ingest', 'Delete the mapping for "{0}"?', $valueMap->source_value),
                                'class' => 'ci-button ci-button--danger',
                            ],
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $this->element('CsvIngest.paginator') ?>
</section>
