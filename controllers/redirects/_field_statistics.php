<?php
    $redirect = $this->formGetModel();
    $redirectId = (int) $redirect->getKey();
    $latestClient = $statisticsHelper->getLatestClient($redirectId);
?>

<div class="scoreboard">
    <div data-control="toolbar">
        <div class="scoreboard-item title-value">
            <h4><?= e(trans('winter.redirect::lang.statistics.activity_last_three_months')); ?></h4>
            <div class="sparkline" style="background-image: url('/winter/redirect/sparkline/<?= $redirectId ?>?crawler=1&preset=3m-large');">
                <img src="/winter/redirect/sparkline/<?= $redirectId ?>?preset=3m-large" alt="">
            </div>
        </div>
    </div>
</div>

<div class="scoreboard">
    <div data-control="toolbar">
        <div class="scoreboard-item title-value">
            <h4><?= e(trans('winter.redirect::lang.statistics.redirects_this_month')); ?></h4>
            <p><?= number_format($statisticsHelper->getTotalThisMonth($redirectId), 0, '', '.') ?></p>
            <p class="description "><?= e(trans('winter.redirect::lang.statistics.previous_month')) ?>: <?= number_format($statisticsHelper->getTotalLastMonth($redirectId), 0, '', '.') ?></p>
        </div>
    </div>
</div>

<div class="scoreboard">
    <div data-control="toolbar">
        <div class="scoreboard-item title-value">
            <h4><?= e(trans('winter.redirect::lang.redirect.hits')); ?></h4>
            <p><?= number_format($redirect->hits, 0, '', '.') ?></p>
            <p class="description"></p>
        </div>
    </div>
</div>

<?php $lastUsedAt = \Backend::dateTime($redirect->last_used_at, ['formatAlias' => 'dateTimeMin']) ?>

<div class="scoreboard">
    <div data-control="toolbar">
        <div class="scoreboard-item title-value">
            <h4><?= e(trans('winter.redirect::lang.redirect.last_used_at')); ?></h4>
            <p><?= empty($lastUsedAt) ? '-' : $lastUsedAt; ?></p>
            <p class="description"></p>
        </div>
    </div>
</div>

<?php $updatedAt = \Backend::dateTime($redirect->updated_at, ['formatAlias' => 'dateTimeMin']) ?>

<div class="scoreboard">
    <div data-control="toolbar">
        <div class="scoreboard-item title-value">
            <h4><?= e(trans('winter.redirect::lang.redirect.updated_at')); ?></h4>
            <p><?= empty($updatedAt) ? '-' : $updatedAt; ?></p>
            <p class="description"></p>
        </div>
    </div>
</div>

<?php if ($redirect->systemRequestLog): ?>
    <hr>
    <i class="text-info icon-info-circle"></i>
    <a href="<?= \Backend::url('system/requestlogs/preview/' . $redirect->systemRequestLog->getKey()) ?>">
        <?= e(trans('winter.redirect::lang.redirect.created_due_to_bad_request')) ?>
    </a>
<?php endif; ?>
