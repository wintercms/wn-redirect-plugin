<div class="row <?= $maxRedirectsResult->getStatusCssClass(); ?>">
    <div class="col-md-3 test">
        <i class="wn-icon-thumbs-o-<?= $maxRedirectsResult->isPassed() ? 'up' : 'down' ?>"></i>
        1. <?= e(trans('winter.redirect::lang.test_lab.loop')); ?>
    </div>
    <div class="col-md-8"><?= $maxRedirectsResult->getMessage(); ?></div>
    <div class="col-md-1 text-right"><?= $maxRedirectsResult->getDuration(); ?> ms</div>
</div>
<div class="row <?= $matchedRedirectResult->getStatusCssClass(); ?>">
    <div class="col-md-3 test">
        <i class="wn-icon-thumbs-o-<?= $matchedRedirectResult->isPassed() ? 'up' : 'down' ?>"></i>
        2. <?= e(trans('winter.redirect::lang.test_lab.match')); ?>
    </div>
    <div class="col-md-8"><?= $matchedRedirectResult->getMessage(); ?></div>
    <div class="col-md-1 text-right"><?= $matchedRedirectResult->getDuration(); ?> ms</div>
</div>
<div class="row <?= $responseCodeResult->getStatusCssClass(); ?>">
    <div class="col-md-3 test">
        <i class="wn-icon-thumbs-o-<?= $responseCodeResult->isPassed() ? 'up' : 'down' ?>"></i>
        3. <?= e(trans('winter.redirect::lang.test_lab.response_http_code')); ?>
    </div>
    <div class="col-md-8"><?= $responseCodeResult->getMessage(); ?></div>
    <div class="col-md-1 text-right"><?= $responseCodeResult->getDuration(); ?> ms</div>
</div>
<div class="row <?= $redirectCountResult->getStatusCssClass(); ?>">
    <div class="col-md-3 test">
        <i class="wn-icon-thumbs-o-<?= $redirectCountResult->isPassed() ? 'up' : 'down' ?>"></i>
        4. <?= e(trans('winter.redirect::lang.test_lab.redirect_count')); ?>
    </div>
    <div class="col-md-8"><?= $redirectCountResult->getMessage(); ?></div>
    <div class="col-md-1 text-right"><?= $redirectCountResult->getDuration(); ?> ms</div>
</div>
<div class="row <?= $finalDestinationResult->getStatusCssClass(); ?>">
    <div class="col-md-3 test">
        <i class="wn-icon-thumbs-o-<?= $finalDestinationResult->isPassed() ? 'up' : 'down' ?>"></i>
        5. <?= e(trans('winter.redirect::lang.test_lab.final_destination')); ?>
    </div>
    <div class="col-md-8"><?= $finalDestinationResult->getMessage(); ?></div>
    <div class="col-md-1 text-right"><?= $finalDestinationResult->getDuration(); ?> ms</div>
</div>
