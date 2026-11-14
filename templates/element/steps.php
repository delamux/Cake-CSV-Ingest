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
    <?php foreach ($steps as $number => $label) :
        $attributes = $number === $current
            ? 'class="ci-steps__item ci-steps__item--current" aria-current="step"'
            : 'class="ci-steps__item"';
        ?>
        <li <?= $attributes ?>>
            <span class="ci-steps__number"><?= $number ?></span> <?= h($label) ?>
        </li>
    <?php endforeach; ?>
</ol>
