<div class="modal-header">
    <button type="button" class="close" data-dismiss="popup">&times;</button>
    <h4 class="modal-title"><?= e(trans('system::lang.request_log.menu_label')); ?></h4>
</div>
<div class="modal-body">
    <?= $this->listRender('requestLog'); ?>
</div>
