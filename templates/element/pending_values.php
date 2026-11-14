<?php
/**
 * CSV texts that could not be linked to a related record yet.
 *
 * @var \Cake\View\View $this
 * @var list<\CsvIngest\Model\Entity\IngestValueMap> $pending
 * @var array<string, array<string|int, string>> $pendingOptions
 * @var list<\CsvIngest\Import\FieldSpec> $fields
 */

use function Cake\Core\h;
use function Cake\I18n\__d;

$labels = [];
foreach ($fields as $field) {
    $labels[$field->name] = $field->label;
}
?>
<fieldset class="ci-fieldset ci-pending">
    <legend class="ci-fieldset__legend">
        <?= h(__d('csv_ingest', 'Pending values ({0})', count($pending))) ?>
    </legend>
    <p class="ci-hint">
        <?= h(__d('csv_ingest', 'Choose the existing record each CSV value refers to. Your choice is remembered for future imports.')) ?>
    </p>
    <div class="ci-table-wrap">
        <table class="ci-table">
            <thead>
                <tr>
                    <th scope="col"><?= h(__d('csv_ingest', 'Field')) ?></th>
                    <th scope="col"><?= h(__d('csv_ingest', 'Value in CSV')) ?></th>
                    <th scope="col"><?= h(__d('csv_ingest', 'Means')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pending as $item) :
                $selectId = 'ci-pending-' . $item->id;
                ?>
                <tr>
                    <td><?= h($labels[$item->field] ?? $item->field) ?></td>
                    <td><label for="<?= h($selectId) ?>"><?= h($item->source_value) ?></label></td>
                    <td>
                        <?= $this->Form->select('pending.' . $item->id, $pendingOptions[$item->field] ?? [], [
                            'empty' => __d('csv_ingest', '— Decide later —'),
                            'id' => $selectId,
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</fieldset>
