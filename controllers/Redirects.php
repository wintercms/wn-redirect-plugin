<?php

declare(strict_types=1);

namespace Winter\Redirect\Controllers;

use Backend\Behaviors;
use Backend\Classes\Controller;
use Backend\Classes\FormField;
use Backend\Facades\Backend;
use Backend\Facades\BackendMenu;
use Backend\Widgets\Form;
use Carbon\Carbon;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use System\Models\RequestLog;
use Throwable;
use Winter\Redirect\Classes\Contracts\CacheManagerInterface;
use Winter\Redirect\Classes\Exceptions\InvalidScheme;
use Winter\Redirect\Classes\Exceptions\NoMatchForRequest;
use Winter\Redirect\Classes\Exceptions\UnableToLoadRules;
use Winter\Redirect\Classes\Observers\RedirectObserver;
use Winter\Redirect\Classes\RedirectManager;
use Winter\Redirect\Classes\RedirectRule;
use Winter\Redirect\Classes\StatisticsHelper;
use Winter\Redirect\Models;
use Winter\Storm\Support\Arr;
use Winter\Storm\Database\Builder;
use Winter\Storm\Database\Model;
use Winter\Storm\Exception\ApplicationException;
use Winter\Storm\Exception\SystemException;
use Winter\Storm\Flash\FlashBag;

/**
 * @mixin Behaviors\FormController
 * @mixin Behaviors\ListController
 * @mixin Behaviors\ReorderController
 * @mixin Behaviors\ImportExportController
 * @mixin Behaviors\RelationController
 */
final class Redirects extends Controller
{
    public $implement = [
        Behaviors\FormController::class,
        Behaviors\ListController::class,
        Behaviors\ReorderController::class,
        Behaviors\ImportExportController::class,
        Behaviors\RelationController::class,
    ];

    public string $formConfig = 'config_form.yaml';

    public array $listConfig = [
        'list' => 'config_list.yaml',
        'requestLog' => 'request-log/config_list.yaml',
    ];

    public string $reorderConfig = 'config_reorder.yaml';
    public string $importExportConfig = 'config_import_export.yaml';
    public string $relationConfig = 'config_relation.yaml';

    public $requiredPermissions = ['winter.redirect.access_redirects'];

    private Request $request;
    private Translator $translator;
    private Dispatcher $dispatcher;
    private CacheManagerInterface $cacheManager;
    private FlashBag $flash;

    public function __construct(
        Request $request,
        Translator $translator,
        Dispatcher $dispatcher,
        CacheManagerInterface $cacheManager
    ) {
        parent::__construct();

        $sideMenuItemCode = in_array($this->action, ['reorder', 'import', 'export'], true)
            ? $this->action
            : 'redirects';

        BackendMenu::setContext('Winter.Redirect', 'redirect', $sideMenuItemCode);

        $this->addCss('/plugins/winter/redirect/assets/css/redirect.css');

        $this->vars['match'] = null;
        $this->vars['statisticsHelper'] = new StatisticsHelper();

        $this->request = $request;
        $this->translator = $translator;
        $this->dispatcher = $dispatcher;
        $this->cacheManager = $cacheManager;
        $this->flash = resolve('flash');
    }

    public function index(): void
    {
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        parent::index();

        if ($this->cacheManager->cachingEnabledButNotSupported()) {
            $this->vars['warningMessage'] = $this->translator->trans('winter.redirect::lang.redirect.cache_warning');
        }
    }

    /**
     * @throws ModelNotFoundException
     * @noinspection PhpStrictTypeCheckingInspection
     */
    public function update($recordId = null, $context = null)
    {
        $this->bodyClass = 'compact-container';

        /** @var Models\Redirect $redirect */
        $redirect = Models\Redirect::query()->findOrFail($recordId);

        /** @noinspection ClassConstantCanBeUsedInspection */
        if ($redirect->getAttribute('target_type') === Models\Redirect::TARGET_TYPE_STATIC_PAGE
            && !class_exists('\Winter\Pages\Classes\Page')
        ) {
            $this->flash->error(
                $this->translator->trans('winter.redirect::lang.flash.static_page_redirect_not_supported')
            );

            return redirect()->back();
        }

        if (!$redirect->isActiveOnDate(Carbon::now())) {
            $this->vars['warningMessage'] = $this->translator->trans(
                'winter.redirect::lang.scheduling.not_active_warning'
            );
        }

        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        parent::update($recordId, $context);
    }

    /**
     * Force rule flushing on update
     */
    public function update_onSave(?string $context = null)
    {
        $redirect = $this->asExtension('FormController')->update_onSave($context);

        $fromUrl = $this->formGetWidget()->getSaveData()['from_url'] ?? null;

        if (!$fromUrl) {
            return $redirect;
        }

        $this->cacheManager->forget($this->cacheManager->cacheKey($fromUrl, 'http'));
        $this->cacheManager->forget($this->cacheManager->cacheKey($fromUrl, 'https'));

        $redirectIds = $this->getAllRedirectIds();

        $this->dispatcher->dispatch('winter.redirect.changed', [
            'redirectIds' => Arr::wrap($redirectIds)
        ]);

        return $redirect;
    }

    // @codingStandardsIgnoreStart

    public function getCacheManager(): CacheManagerInterface
    {
        return $this->cacheManager;
    }

    public function create_onSave(?string $context = null): RedirectResponse
    {
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $redirect = parent::create_onSave($context);

        if ($this->request->has('new')) {
            return Backend::redirect('winter/redirect/redirects/create');
        }

        return $redirect;
    }

    public function index_onDelete(): array
    {
        $redirectIds = $this->getCheckedIds();

        Models\Redirect::destroy($redirectIds);

        $this->dispatcher->dispatch('winter.redirect.changed', [
            'redirectIds' => Arr::wrap($redirectIds)
        ]);

        return $this->listRefresh();
    }

    public function index_onEnable(): array
    {
        $redirectIds = $this->getCheckedIds();

        Models\Redirect::query()
            ->whereIn('id', $redirectIds)
            ->update(['is_enabled' => 1]);

        $this->dispatcher->dispatch('winter.redirect.changed', [
            'redirectIds' => Arr::wrap($redirectIds)
        ]);

        return $this->listRefresh();
    }

    public function index_onDisable(): array
    {
        $redirectIds = $this->getCheckedIds();

        Models\Redirect::query()
            ->whereIn('id', $redirectIds)
            ->update(['is_enabled' => 0]);

        $this->dispatcher->dispatch('winter.redirect.changed', [
            'redirectIds' => Arr::wrap($redirectIds)
        ]);

        return $this->listRefresh();
    }

    public function index_onResetStatistics(): array
    {
        $redirectIds = $this->getCheckedIds();

        Models\Redirect::query()
            ->whereIn('id', $redirectIds)
            ->update(['hits' => 0]);

        // When DB does not support cascading delete.
        Models\Client::query()
            ->whereIn('redirect_id', $redirectIds)
            ->delete();

        $this->dispatcher->dispatch('winter.redirect.changed', [
            'redirectIds' => Arr::wrap($redirectIds)
        ]);

        return $this->listRefresh();
    }

    public function index_onClearCache(): void
    {
        /** @var CacheManagerInterface $cacheManager */
        $cacheManager = resolve(CacheManagerInterface::class);
        $cacheManager->flush();

        $this->flash->success($this->translator->trans('winter.redirect::lang.flash.cache_cleared_success'));
    }

    /**
     * @throws SystemException
     */
    public function index_onLoadActions(): string
    {
        return (string) $this->makePartial('popup_actions');
    }

    public function index_onResetAllStatistics(): array
    {
        $redirectIds = $this->getAllRedirectIds();

        RedirectObserver::stopHandleChanges();

        Models\Redirect::query()->update(['hits' => 0]);
        Models\Client::query()->delete();

        $this->flash->success($this->translator->trans('winter.redirect::lang.flash.statistics_reset_success'));

        $this->dispatcher->dispatch('winter.redirect.changed', [
            'redirectIds' => Arr::wrap($redirectIds)
        ]);

        return $this->listRefresh();
    }

    public function index_onEnableAllRedirects(): array
    {
        return $this->toggleRedirects(true);
    }

    public function index_onDisableAllRedirects(): array
    {
        return $this->toggleRedirects(false);
    }

    private function toggleRedirects(bool $enabled): array
    {
        $redirectIds = $this->getAllRedirectIds();

        Models\Redirect::query()
            ->update(['is_enabled' => $enabled]);

        $this->flash->success($this->translator->trans('winter.redirect::lang.flash.disabled_all_redirects_success'));

        $this->dispatcher->dispatch('winter.redirect.changed', [
            'redirectIds' => Arr::wrap($redirectIds)
        ]);

        return $this->listRefresh();
    }

    public function index_onDeleteAllRedirects(): array
    {
        $redirectIds = $this->getAllRedirectIds();

        Models\Redirect::query()
            ->delete();

        $this->flash->success($this->translator->trans('winter.redirect::lang.flash.deleted_all_redirects_success'));

        $this->dispatcher->dispatch('winter.redirect.changed', [
            'redirectIds' => Arr::wrap($redirectIds)
        ]);

        return $this->listRefresh();
    }

    // @codingStandardsIgnoreEnd

    /**
     * @throws SystemException
     */
    public function onShowStatusCodeInfo(): string
    {
        return (string) $this->makePartial('status_code_info', [], false);
    }

    public function listExtendQuery(Builder $query, $definition = null): void
    {
        if ($definition === 'requestLog') {
            $query->whereNull('winter_redirect_redirect_id');
        }
    }

    public function formExtendFields(Form $host, array $fields = []): void
    {
        $disableFields = [
            'from_url',
            'to_url',
            'cms_page',
            'target_type',
            'match_type',
        ];

        foreach ($disableFields as $disableField) {
            /** @var FormField $field */
            $field = $host->getField($disableField);
            $field->disabled = $host->model->getAttribute('system');
        }

        if (!Models\Settings::isTestLabEnabled()) {
            $host->removeTab('winter.redirect::lang.tab.tab_test_lab');
        }

        if ($this->request->isMethod(Request::METHOD_GET)) {
            $this->formExtendRefreshFields($host, $fields);
        }
    }

    public function formExtendRefreshFields(Form $host, array $fields): void
    {
        if ($fields['status_code']->value
            && strpos((string) $fields['status_code']->value, '4') === 0
        ) {
            $host->getField('to_url')->hidden = true;
            $host->getField('static_page')->hidden = true;
            $host->getField('cms_page')->hidden = true;
            $host->getField('to_scheme')->hidden = true;
            return;
        }

        switch ($fields['target_type']->value) {
            case Models\Redirect::TARGET_TYPE_CMS_PAGE:
                $host->getField('to_url')->hidden = true;
                $host->getField('static_page')->hidden = true;
                $host->getField('cms_page')->hidden = false;
                break;
            case Models\Redirect::TARGET_TYPE_STATIC_PAGE:
                $host->getField('to_url')->hidden = true;
                $host->getField('static_page')->hidden = false;
                $host->getField('cms_page')->hidden = true;
                break;
            default:
                $host->getField('to_url')->hidden = false;
                $host->getField('static_page')->hidden = true;
                $host->getField('cms_page')->hidden = true;
                break;
        }
    }

    public function listInjectRowClass(Model $record): string
    {
        if ($record instanceof Models\Redirect
            && !$record->isActiveOnDate(Carbon::now())
        ) {
            return 'special';
        }

        return '';
    }

    /**
     * @throws ApplicationException
     * @throws SystemException
     */
    public function onTest(): array
    {
        $inputPath = $this->request->get('inputPath');
        $redirect = new Models\Redirect($this->request->get('Redirect'));

        try {
            $rule = RedirectRule::createWithModel($redirect);
            $manager = RedirectManager::createWithRule($rule);
            $testDate = Carbon::createFromFormat('Y-m-d', $this->request->get('test_date', date('Y-m-d')));
            $manager->setMatchDate($testDate);
            $match = $manager->match($inputPath, $this->request->get('test_scheme', $this->request->getScheme()));
        } catch (NoMatchForRequest | InvalidScheme | UnableToLoadRules $exception) {
            $match = false;
        } catch (Throwable $throwable) {
            throw new ApplicationException($throwable->getMessage());
        }

        return [
            '#testResult' => $this->makePartial('redirect_test_result', [
                'match' => $match,
                'url' => $match && isset($manager) ? $manager->getLocation($match) : '',
            ]),
        ];
    }

    /**
     * @throws SystemException
     */
    public function onOpenRequestLog(): string
    {
        $this->makeLists();

        return $this->makePartial('request-log/modal');
    }

    /**
     * @throws ModelNotFoundException
     */
    public function onCreateRedirectFromRequestLogItems(): array
    {
        $checkedIds = $this->getCheckedIds();
        $redirectsCreated = 0;

        foreach ($checkedIds as $checkedId) {
            /** @var RequestLog $requestLog */
            $requestLog = RequestLog::query()
                ->findOrFail($checkedId);

            $url = $this->parseRequestLogItemUrl((string) $requestLog->getAttribute('url'));

            if ($url === '') {
                continue;
            }

            $redirect = Models\Redirect::create([
                'match_type' => Models\Redirect::TYPE_EXACT,
                'target_type' => Models\Redirect::TARGET_TYPE_PATH_URL,
                'from_url' => $url,
                'to_url' => '/',
                'status_code' => 301,
                'is_enabled' => false,
            ]);

            $requestLog->update([
                'winter_redirect_redirect_id' => $redirect->getKey()
            ]);

            $redirectsCreated++;
        }

        if ($redirectsCreated > 0) {
            $this->flash->success($this->translator->trans(
                'winter.redirect::lang.flash.success_created_redirects',
                [
                    'count' => $redirectsCreated,
                ]
            ));
        }

        return $this->listRefresh();
    }

    private function getCheckedIds(): array
    {
        if (($checkedIds = $this->request->get('checked'))
            && is_array($checkedIds)
            && count($checkedIds)
        ) {
            return array_map(static function ($checkedId) {
                return (int) $checkedId;
            }, $checkedIds);
        }

        return [];
    }

    private function getAllRedirectIds(): array
    {
        return Models\Redirect::query()
            ->get()
            ->pluck('id')
            ->toArray();
    }

    private function parseRequestLogItemUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if ($path === false || $path === '/' || $path === '') {
            return '';
        }

        // Using `parse_url($url, PHP_URL_QUERY)` will result in a string of sorted query params (2.0.23):
        // e.g ?a=z&z=a becomes ?z=a&a=z
        // So let's just grab the query part using string functions to make sure whe have the exact query string.
        $questionMarkPosition = strpos($url, '?');

        if ($questionMarkPosition !== false) {
            $path .= substr($url, $questionMarkPosition);
        }

        return $path;
    }
}
