            <div class="loading-indicator-container">
                <button
                    type="submit"
                    data-request="onSave"
                    data-request-data="new:1"
                    data-hotkey="ctrl+shift+s, cmd+shift+s"
                    data-load-indicator="<?= e(trans('backend::lang.form.saving')); ?>"
                    class="btn btn-primary">
                    <?= e(trans('winter.redirect::lang.buttons.create_and_new')); ?>
                </button>
                <button
                    type="submit"
                    data-request="onSave"
                    data-hotkey="ctrl+s, cmd+s"
                    data-load-indicator="<?= e(trans('backend::lang.form.saving')); ?>"
                    class="btn btn-primary">
                    <?= e(trans('backend::lang.form.create')); ?>
                </button>
                <button
                    type="button"
                    data-request="onSave"
                    data-request-data="close:1"
                    data-hotkey="ctrl+enter, cmd+enter"
                    data-load-indicator="<?= e(trans('backend::lang.form.saving')); ?>"
                    class="btn btn-default">
                    <?= e(trans('backend::lang.form.create_and_close')); ?>
                </button>
                <span class="btn-text">
                    <?= e(trans('backend::lang.form.or')); ?> <a href="<?= Backend::url('winter/redirect/redirects'); ?>"><?= e(trans('backend::lang.form.cancel')); ?></a>
                </span>
            </div>
