<?= Form::open(['class' => 'layout']); ?>

<input type="hidden" id="redirectCount" value="<?= $redirectCount ?>">

<div class="row test-lab">
    <?php if ($redirectCount !== 0): ?>
    <div class="col-md-9 col-sm-8">
        <div class="progress">
            <div class="progress-bar"
                 id="progressBar"
                 role="progressbar"
                 aria-valuenow="0"
                 aria-valuemin="0"
                 aria-valuemax="100"
                 style="width:0">
            </div>
        </div>
        <p id="progress" data-initial="<?= e(trans('winter.redirect::lang.test_lab.start_tests_description')); ?>">
            <?= e(trans('winter.redirect::lang.test_lab.start_tests_description')); ?>
        </p>
    </div>
    <div class="col-md-3 col-sm-4" id="testButtonWrapper">
        <?= $this->makePartial('test_button', ['redirectCount' => $redirectCount]); ?>
    </div>
    <div class="clearfix"></div>
    <div class="col-md-12" id="testerResults"></div>
    <?php else: ?>
    <div class="col-md-12">
        <div class="callout fade in callout-warning">
            <button type="button"
                    class="close"
                    data-dismiss="callout"
                    aria-hidden="true">&times;
            </button>
            <div class="content">
                <p><?= e(trans('winter.redirect::lang.test_lab.no_redirects')); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?= Form::close(); ?>

<div id="loader" class="popup-backdrop fade in hidden">
    <div class="modal-content popup-loading-indicator">
        <div><?= e(trans('winter.redirect::lang.test_lab.executing_tests')); ?></div>
        <button class="btn btn-danger" onclick="testerStop()">
            <?= e(trans('winter.redirect::lang.buttons.stop')); ?>
        </button>
    </div>
</div>
