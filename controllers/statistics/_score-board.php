<div class="scoreboard">
    <div data-control="toolbar">
        <div class="scoreboard-item control-chart"
             data-control="chart-pie"
             data-center-text="<?= number_format($totalActiveRedirects, 0, '', '.'); ?>"
             data-size="120">
            <ul>
                <?php foreach ($activeRedirects as $group => $activeRedirect): ?>
                <li><?= e(trans('winter.redirect::lang.redirect.' . \Winter\Redirect\Models\Redirect::$statusCodes[$group])); ?>
                    <span><?= count($activeRedirect); ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="scoreboard-item title-value">
            <h4><?= e(trans('winter.redirect::lang.statistics.requests_redirected')); ?></h4>
            <p><?= number_format($totalRedirectsServed, 0, '', '.'); ?></p>
            <p class="description"><?= e(trans('winter.redirect::lang.statistics.all_time')); ?></p>
        </div>
        <div class="scoreboard-item title-value">
            <h4><?= e(trans('winter.redirect::lang.statistics.active_redirects')); ?></h4>
            <p><?= number_format($totalActiveRedirects, 0, '', '.'); ?></p>
        </div>
        <div class="scoreboard-item title-value">
            <h4><?= e(trans('winter.redirect::lang.statistics.redirects_this_month')); ?></h4>
            <p><?= number_format($totalThisMonth, 0, '', '.'); ?></p>
            <p class="description "><?= e(trans('winter.redirect::lang.statistics.previous_month')); ?>: <?= number_format($totalLastMonth, 0, '', '.'); ?></p>
        </div>
        <?php if ($latestClient): ?>
        <div class="scoreboard-item title-value">
            <h4><?= e(trans('winter.redirect::lang.statistics.latest_redirected_requests')); ?></h4>
            <p class="<?= $latestClient->crawler ? 'wn-icon-robot' : 'wn-icon-user' ?>"><?= e($latestClient->redirect->from_url) ?></p>
            <p class="description"><?= e($latestClient->timestamp) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>
