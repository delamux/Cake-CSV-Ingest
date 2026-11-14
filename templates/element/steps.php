<?php
/**
 * @var \Cake\View\View $this
 * @var int $current
 */

use function Cake\Core\h;
use function Cake\I18n\__d;

$steps = [1 => __d('csv_ingest', 'Upload file'), 2 => __d('csv_ingest', 'Map columns and import')];
?>
<ol class="ci-steps">
    <?php foreach ($steps as $number => $label) : ?>
        <li class="ci-steps__item<?= $number === $current ? ' ci-steps__item--current' : '' ?>"
            <?= $number === $current ? 'aria-current="step"' : '' ?>>
            <span class="ci-steps__number"><?= $number ?></span> <?= h($label) ?>
        </li>
    <?php endforeach; ?>
</ol>
