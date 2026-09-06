<?php $testHost = $testHost ?? null; ?>
<?php if ($match === false): ?>
    <div class="form-group">
        <label style="color: red"><?= e(trans('winter.redirect::lang.test.no_match_label')); ?></label>
        <div class="form-control wn-icon-thumbs-down">
            <?= e(trans('winter.redirect::lang.test.no_match')); ?>
        </div>
        <?php if ($testHost): ?>
            <p class="help-block">
                <?= e(trans('winter.redirect::lang.test.tested_on_host', ['host' => $testHost])); ?>
            </p>
        <?php endif; ?>
    </div>
<?php elseif ($match !== null): ?>
    <div class="form-group">
        <label style="color: green"><?= e(trans('winter.redirect::lang.test.match_success_label')); ?></label>
        <div class="form-control wn-icon-thumbs-up">
            <?= $url ?> <sup>(<?= $match->getStatusCode(); ?>)</sup>
        </div>
        <?php if ($testHost): ?>
            <p class="help-block">
                <?= e(trans('winter.redirect::lang.test.tested_on_host', ['host' => $testHost])); ?>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>
