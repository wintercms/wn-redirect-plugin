<div class="toolbar" data-control="toolbar">
    <a href="<?= Backend::url('winter/redirect/redirects/create'); ?>"
       class="btn btn-primary wn-icon-plus">
        <?= e(trans('winter.redirect::lang.buttons.new_redirect')); ?>
    </a>

    <?php if ((int) \System\Models\LogSetting::get('log_requests') === 1): ?>
        <a href="#"
           class="btn btn-default wn-icon-plus"
           data-control="popup"
           data-size="giant"
           data-handler="onOpenRequestLog"
           data-stripe-load-indicator>
            <?= e(trans('winter.redirect::lang.buttons.from_request_log')); ?>
        </a>
    <?php endif; ?>

    <a class="btn btn-default wn-icon-sort-amount-asc"
       href="<?= Backend::url('winter/redirect/redirects/reorder'); ?>">
        <?= e(trans('winter.redirect::lang.buttons.reorder_redirects')); ?>
    </a>

    <div class="btn-group dropdown dropdown-fixed" data-control="bulk-actions">
        <button
            data-primary-button
            type="button"
            class="btn btn-default btn-bulk-action"
            data-request="onEnable"
            onclick="$(this).data('request-data', { checked: $('.control-list').listWidget('getChecked') })"
            data-trigger-action="enable"
            data-trigger=".control-list input[type=checkbox]"
            data-trigger-condition="checked"
            data-request-success="$('.btn-bulk-action').prop('disabled', true)"
            data-stripe-load-indicator
        >
            <?= e(trans('winter.redirect::lang.buttons.enable')); ?>
        </button>
        <button
            type="button"
            class="btn btn-default dropdown-toggle btn-bulk-action"
            data-trigger-action="enable"
            data-trigger=".control-list input[type=checkbox]"
            data-trigger-condition="checked"
            data-toggle="dropdown"
        >
            <span class="caret"></span>
        </button>
        <ul class="dropdown-menu" data-dropdown-title="<?= e(trans('winter.redirect::lang.buttons.bulk_actions')); ?>">
            <li>
                <a
                    href="javascript:;"
                    class="wn-icon-check-square-o"
                    onclick="$(this).data('request-data', { checked: $('.control-list').listWidget('getChecked') })"
                    data-request="onEnable"
                    data-request-success="$('.btn-bulk-action').prop('disabled', true)"
                    data-stripe-load-indicator
                >
                    <?= e(trans('winter.redirect::lang.buttons.enable')); ?>
                </a>
            </li>
            <li>
                <a
                    href="javascript:;"
                    class="wn-icon-square-o"
                    onclick="$(this).data('request-data', { checked: $('.control-list').listWidget('getChecked') })"
                    data-request="onDisable"
                    data-request-success="$('.btn-bulk-action').prop('disabled', true)"
                    data-stripe-load-indicator
                >
                    <?= e(trans('winter.redirect::lang.buttons.disable')); ?>
                </a>
            </li>
            <li role="separator" class="divider"></li>
            <li>
                <a
                    href="javascript:;"
                    class="wn-icon-undo"
                    onclick="$(this).data('request-data', { checked: $('.control-list').listWidget('getChecked') })"
                    data-request="onResetStatistics"
                    data-request-success="$('.btn-bulk-action').prop('disabled', true)"
                    data-stripe-load-indicator
                    data-confirm="<?= e(trans('winter.redirect::lang.redirect.general_confirm')) ?>"
                >
                    <?= e(trans('winter.redirect::lang.buttons.reset_statistics')); ?>
                </a>
            </li>
            <li role="separator" class="divider"></li>
            <li>
                <a
                    href="javascript:;"
                    class="wn-icon-trash-o"
                    onclick="$(this).data('request-data', { checked: $('.control-list').listWidget('getChecked') })"
                    data-request="onDelete"
                    data-request-success="$('.btn-bulk-action').prop('disabled', true)"
                    data-stripe-load-indicator
                    data-confirm="<?= e(trans('winter.redirect::lang.redirect.delete_confirm')); ?>"
                >
                    <?= e(trans('winter.redirect::lang.buttons.delete')); ?>
                </a>
            </li>
        </ul>
    </div>
    <button class="btn btn-default wn-icon-ellipsis-h"
            data-control="popup"
            data-handler="onLoadActions"
            data-keyboard="true"
            data-size="small">
        <?= e(trans('winter.redirect::lang.buttons.bulk_actions')); ?>
    </button>
</div>
