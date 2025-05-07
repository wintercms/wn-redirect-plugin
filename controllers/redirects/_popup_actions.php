<div class="modal-header">
    <button type="button"
            class="close"
            data-dismiss="modal"
            aria-hidden="true">&times;</button>
    <h4 class="modal-title"><?= e(trans('winter.redirect::lang.buttons.bulk_actions')); ?></h4>
</div>
<div class="modal-body actions">
    <div class="form-group">
    <?php if ($this->getCacheManager()->cachingEnabledAndSupported()): ?>
        <button class="btn btn-default btn-block"
                data-request="onClearCache"
                data-stripe-load-indicator>
            <?= e(trans('winter.redirect::lang.buttons.clear_cache')); ?>
        </button>
    <?php elseif ($this->getCacheManager()->cachingEnabledButNotSupported()): ?>
        <button class="btn btn-default btn-block"
                disabled="disabled">
            <?= e(trans('winter.redirect::lang.buttons.clear_cache')); ?>
        </button>
    <?php endif; ?>
    </div>
    <div class="form-group">
        <button class="btn btn-default btn-block"
                data-request="onResetAllStatistics"
                data-request-confirm="<?= e(trans('winter.redirect::lang.redirect.general_confirm')); ?>">
            <?= e(trans('winter.redirect::lang.buttons.reset_all')); ?>
        </button>
    </div>
    <div class="form-group">
        <button class="btn btn-default btn-block"
                data-request="onEnableAllRedirects"
                data-request-confirm="<?= e(trans('winter.redirect::lang.redirect.general_confirm')); ?>">
            <strong><u><?= e(trans('winter.redirect::lang.buttons.enable')); ?></u></strong> <?= e(trans('winter.redirect::lang.buttons.all_redirects')); ?>
        </button>
    </div>
    <div class="form-group">
        <button class="btn btn-default btn-block"
                data-request="onDisableAllRedirects"
                data-request-confirm="<?= e(trans('winter.redirect::lang.redirect.general_confirm')); ?>">
            <strong><u><?= e(trans('winter.redirect::lang.buttons.disable')); ?></u></strong> <?= e(trans('winter.redirect::lang.buttons.all_redirects')); ?>
        </button>
    </div>
    <button class="btn btn-danger btn-block"
            data-request="onDeleteAllRedirects"
            data-request-confirm="<?= e(trans('winter.redirect::lang.redirect.general_confirm')); ?>">
        <strong><u><?= e(trans('winter.redirect::lang.buttons.delete')); ?></u></strong> <?= e(trans('winter.redirect::lang.buttons.all_redirects')); ?>
    </button>
    <hr>
    <div class="form-group">
        <button class="btn btn-secondary btn-block"
                data-dismiss="modal">
            <?= e(trans('backend::lang.form.cancel')); ?>
        </button>
    </div>
</div>
