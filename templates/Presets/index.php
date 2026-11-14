<?php
/**
 * @var \Cake\View\View $this
 * @var iterable<\CsvIngest\Model\Entity\IngestPreset> $presets
 * @var string|null $target
 * @var string|int|null $ownerId
 */

use function Cake\Core\h;
use function Cake\I18n\__d;

$this->Html->css('CsvIngest.csv-ingest', ['block' => true]);
?>
<section class="ci-admin">
    <header class="ci-admin__header">
        <h1><?= h(__d('csv_ingest', 'Import presets')) ?></h1>
        <?php if ($target !== null) : ?>
            <p class="ci-hint">
                <?= h(__d('csv_ingest', 'Showing presets for {0}.', $target)) ?>
                <?= $this->Html->link(__d('csv_ingest', 'Show all'), ['action' => 'index']) ?>
            </p>
        <?php endif; ?>
    </header>

    <div class="ci-table-wrap">
        <table class="ci-table">
            <thead>
                <tr>
                    <th scope="col"><?= $this->Paginator->sort('name', __d('csv_ingest', 'Name')) ?></th>
                    <th scope="col"><?= $this->Paginator->sort('target', __d('csv_ingest', 'Imports into')) ?></th>
                    <th scope="col"><?= h(__d('csv_ingest', 'Visibility')) ?></th>
                    <th scope="col"><?= $this->Paginator->sort('modified', __d('csv_ingest', 'Updated')) ?></th>
                    <th scope="col"><span class="ci-visually-hidden"><?= h(__d('csv_ingest', 'Actions')) ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($presets as $preset) : ?>
                <tr>
                    <td><?= h($preset->name) ?></td>
                    <td><?= $this->Html->link($preset->target, ['action' => 'index', '?' => ['target' => $preset->target]]) ?></td>
                    <td>
                        <?= h($preset->owner_id === null ? __d('csv_ingest', 'Shared') : __d('csv_ingest', 'Personal')) ?>
                    </td>
                    <td><?= h((string)$preset->modified) ?></td>
                    <td class="ci-table__actions">
                        <?= $this->Form->postLink(
                            __d('csv_ingest', 'Delete'),
                            ['action' => 'delete', $preset->id],
                            [
                                'confirm' => __d('csv_ingest', 'Delete the preset "{0}"?', $preset->name),
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
