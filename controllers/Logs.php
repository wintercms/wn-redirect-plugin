<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Winter\Redirect\Controllers;

use Backend\Behaviors\ListController;
use Backend\Classes\Controller;
use BackendMenu;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use Winter\Storm\Flash\FlashBag;
use Psr\Log\LoggerInterface;
use Throwable;
use Winter\Redirect\Models\RedirectLog;

/**
 * @mixin ListController
 */
final class Logs extends Controller
{
    public $implement = [
        ListController::class
    ];

    public $requiredPermissions = ['winter.redirect.access_redirects'];
    public string $listConfig = 'config_list.yaml';
    private Request $request;
    private Translator $translator;
    private FlashBag $flash;
    private LoggerInterface $log;

    public function __construct(Request $request, Translator $translator, LoggerInterface $log)
    {
        parent::__construct();

        BackendMenu::setContext('Winter.Redirect', 'redirect', 'logs');

        $this->addCss('/plugins/winter/redirect/assets/css/redirect.css');

        $this->request = $request;
        $this->translator = $translator;
        $this->flash = resolve('flash');
        $this->log = $log;
    }

    public function onRefresh(): array
    {
        return $this->listRefresh();
    }

    public function onEmptyLog(): array
    {
        try {
            RedirectLog::query()->truncate();
            $this->flash->success($this->translator->trans('winter.redirect::lang.flash.truncate_success'));
        } catch (Throwable $e) {
            $this->log->warning($e);
        }

        return $this->listRefresh();
    }

    public function onDelete(): array
    {
        if (($checkedIds = $this->request->get('checked', []))
            && is_array($checkedIds)
            && count($checkedIds)
        ) {
            foreach ($checkedIds as $recordId) {
                try {
                    /** @var RedirectLog $record */
                    $record = RedirectLog::query()->findOrFail($recordId);
                    $record->delete();
                } catch (Throwable $e) {
                    $this->log->warning($e);
                }
            }

            $this->flash->success($this->translator->trans('winter.redirect::lang.flash.delete_selected_success'));
        } else {
            $this->flash->error($this->translator->trans('backend::lang.list.delete_selected_empty'));
        }

        return $this->listRefresh();
    }
}
