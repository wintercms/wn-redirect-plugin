<style type="text/css">
    .modal-dialog .list-header {
        padding-left: 0;
        padding-right: 0;
    }
    .modal-dialog div[data-control=toolbar] .checkbox {
        margin-top: 4px;
        margin-bottom: 0;
    }
    .modal-dialog div[data-control=toolbar] .btn {
        float: left;
    }
</style>
<script type="text/javascript">
    function onAddButtonClick() {
        $('#addRedirectFromRequestLogItemButton').data(
            'request-data',
            {
                checked: $('.modal-dialog .control-list').listWidget('getChecked')
            }
        );
    }
</script>
<div data-control="toolbar">
    <button class="btn btn-default wn-icon-plus"
            disabled="disabled"
            id="addRedirectFromRequestLogItemButton"
            onclick="onAddButtonClick()"
            data-request="onCreateRedirectFromRequestLogItems"
            data-trigger-action="enable"
            data-trigger=".modal-dialog .control-list input[type=checkbox]"
            data-trigger-condition="checked"
            data-request-success="$('.modal').trigger('close.oc.popup');"
            data-stripe-load-indicator>
        <?= e(trans('winter.redirect::lang.buttons.create_redirects')); ?>
    </button>
</div>
