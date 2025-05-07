<div class="row heading">
    <div class="col-md-8">
        <h5><?= e($testPath) ?> <?php if (!empty($redirect->to_url)): ?><i class="icon-arrow-right"></i> <?= e($redirect->to_url) ?><?php endif; ?></h5>
    </div>
    <div class="col-md-4 text-right">
        <div class="loading-indicator-container">
            <div class="toolbar">
                <a class="btn btn-outline-default btn-xs toolbar-item"
                   href="<?= Backend::url('winter/redirect/redirects/update/' . $redirect->id); ?>">
                    <i class="wn-icon-pencil"></i><?= e(trans('winter.redirect::lang.test_lab.edit')); ?>
                </a>
                <a class="btn btn-outline-danger btn-xs toolbar-item"
                   data-request="onExclude"
                   data-request-data="id: <?= $redirect->id ?>"
                   data-request-confirm="<?= e(trans('winter.redirect::lang.test_lab.exclude_confirm')); ?>"
                   data-load-indicator="<?= e(trans('winter.redirect::lang.test_lab.exclude_indicator')); ?>"
                   href="javascript:">
                    <i class="wn-icon-ban"></i><?= e(trans('winter.redirect::lang.test_lab.exclude')); ?>
                </a>
                <a class="btn btn-outline-success btn-xs toolbar-item"
                   data-request="onReRun"
                   data-request-data="id: <?= $redirect->id ?>"
                   data-load-indicator="<?= e(trans('winter.redirect::lang.test_lab.re_run_indicator')); ?>"
                   href="javascript:">
                    <i class="wn-icon-refresh"></i><?= e(trans('winter.redirect::lang.test_lab.re_run')); ?>
                </a>
            </div>
        </div>
    </div>
</div>
<div class="tester-result" id="testerResult<?= $redirect->id ?>">
    <?= $this->makePartial('tester_result_items', $testResults); ?>
</div>
