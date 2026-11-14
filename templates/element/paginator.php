<?php
/**
 * @var \Cake\View\View $this
 */

use function Cake\I18n\__d;

if (!$this->Paginator->hasPrev() && !$this->Paginator->hasNext()) {
    return;
}
?>
<nav class="ci-pagination" aria-label="<?= \Cake\Core\h(__d('csv_ingest', 'Pagination')) ?>">
    <ul class="ci-pagination__list">
        <?= $this->Paginator->prev('‹ ' . __d('csv_ingest', 'previous')) ?>
        <?= $this->Paginator->numbers() ?>
        <?= $this->Paginator->next(__d('csv_ingest', 'next') . ' ›') ?>
    </ul>
    <p class="ci-hint"><?= $this->Paginator->counter(__d('csv_ingest', 'Page {{page}} of {{pages}}, {{count}} records')) ?></p>
</nav>
