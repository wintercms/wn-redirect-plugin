<h3><?= e(trans('winter.redirect::lang.statistics.redirects_per_month')); ?></h3>
<?php if ($redirectHitsPerMonth): ?>
    <div class="control-status-list">
        <ul>
            <?php foreach ($redirectHitsPerMonth as $key => $record): ?>
            <li>
                <span class="status-icon info"><?= $key + 1 ?></span>
                <span class="status-text"><?= date('F', mktime(0, 0, 0, (int) $record['month'])); ?> <?= e($record['year']) ?></span>
                <span class="status-label primary"><?= number_format($record['hits'], 0, null, '.'); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php else: ?>
    <p class="help-block"><i class="wn-icon-meh-o"></i> <?= e(trans('winter.redirect::lang.statistics.no_data')); ?></p>
<?php endif; ?>
