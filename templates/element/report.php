<?php
/**
 * Result of an import run.
 *
 * @var \Cake\View\View $this
 * @var \CsvIngest\Import\IngestReport $report
 * @var list<\CsvIngest\Import\RowOutcome> $outcomes
 */

use CsvIngest\Import\RowOutcome;

use function Cake\Core\h;
use function Cake\I18n\__d;
?>
<section class="ci-report" aria-labelledby="ci-report-title">
    <h2 class="ci-report__title" id="ci-report-title">
        <?= h(__d('csv_ingest', 'Result')) ?>
        <?php if ($report->dryRun) : ?>
            <span class="ci-badge ci-badge--info"><?= h(__d('csv_ingest', 'Dry run')) ?></span>
        <?php endif; ?>
    </h2>

    <ul class="ci-report__counts">
        <?php foreach ($outcomes as $outcome) : ?>
            <li class="ci-count ci-count--<?= h($outcome->value) ?>" data-ci-outcome="<?= h($outcome->value) ?>">
                <span class="ci-count__value"><?= $report->count($outcome) ?></span>
                <span class="ci-count__label"><?= h($outcome->label()) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($report->isTruncated()) : ?>
        <p class="ci-hint ci-hint--warning">
            <?= h(__d('csv_ingest', 'The row limit was reached; the remaining rows were not processed.')) ?>
        </p>
    <?php endif; ?>

    <?php if ($report->unresolved() !== []) : ?>
        <p class="ci-hint ci-hint--warning">
            <?= h(__d('csv_ingest', 'Some values could not be linked to existing records. Assign them below and import again.')) ?>
        </p>
    <?php endif; ?>

    <?php if ($report->hasFailures()) : ?>
        <details class="ci-report__failures" open>
            <summary><?= h(__d('csv_ingest', '{0} rows failed', $report->count(RowOutcome::Failed))) ?></summary>
            <div class="ci-table-wrap">
                <table class="ci-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= h(__d('csv_ingest', 'Line')) ?></th>
                            <th scope="col"><?= h(__d('csv_ingest', 'Column')) ?></th>
                            <th scope="col"><?= h(__d('csv_ingest', 'Problem')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($report->failures() as $failure) : ?>
                        <?php foreach ($failure->messages as $column => $messages) : ?>
                            <tr>
                                <td><?= $failure->line ?></td>
                                <td><?= h((string)$column) ?></td>
                                <td><?= h(implode(' ', $messages)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    <?php endif; ?>
</section>
