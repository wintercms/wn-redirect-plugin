<?php

declare(strict_types=1);

namespace Winter\Redirect\Models;

use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Validator;
use System\Models\RequestLog;
use Winter\Redirect\Classes\OptionHelper;
use Winter\Storm\Support\Arr;
use Winter\Storm\Database\Builder;
use Winter\Storm\Database\Model;
use Winter\Storm\Database\Relations\HasMany;
use Winter\Storm\Database\Traits\Sortable;
use Winter\Storm\Database\Traits\Validation;

/**
 * @method static Redirect|Builder enabled()
 * @method static Redirect|Builder testLabEnabled()
 * @property RequestLog|null $systemRequestLog
 */
final class Redirect extends Model
{
    use Sortable {
        setSortableOrder as traitSetSortableOrder;
    }

    use Validation {
        makeValidator as traitMakeValidator;
    }

    // Types
    public const TYPE_EXACT = 'exact';
    public const TYPE_PLACEHOLDERS = 'placeholders';
    public const TYPE_REGEX = 'regex';

    // Target Types
    public const TARGET_TYPE_PATH_URL = 'path_or_url';
    public const TARGET_TYPE_CMS_PAGE = 'cms_page';
    public const TARGET_TYPE_STATIC_PAGE = 'static_page';
    public const TARGET_TYPE_NONE = 'none';

    // Scheme options
    public const SCHEME_HTTP = 'http';
    public const SCHEME_HTTPS = 'https';
    public const SCHEME_AUTO = 'auto';

    public static array $types = [
        self::TYPE_EXACT,
        self::TYPE_PLACEHOLDERS,
        self::TYPE_REGEX,
    ];

    public static array $targetTypes = [
        self::TARGET_TYPE_PATH_URL,
        self::TARGET_TYPE_CMS_PAGE,
        self::TARGET_TYPE_STATIC_PAGE,
        self::TARGET_TYPE_NONE,
    ];

    public static array $statusCodes = [
        301 => 'permanent',
        302 => 'temporary',
        303 => 'see_other',
        404 => 'not_found',
        410 => 'gone',
    ];

    public $table = 'winter_redirect_redirects';

    /**
     * Validation rules.
     */
    public array $rules = [
        'from_url' => 'required',
        'from_scheme' => 'in:http,https,auto',
        'to_url' => 'different:from_url|required_if:target_type,path_or_url',
        'to_scheme' => 'in:http,https,auto',
        'cms_page' => 'required_if:target_type,cms_page',
        'static_page' => 'required_if:target_type,static_page',
        'match_type' => 'required|in:exact,placeholders,regex',
        'target_type' => 'required|in:path_or_url,cms_page,static_page,none',
        'status_code' => 'required|in:301,302,303,404,410',
        'sort_order' => 'numeric',
    ];

    /**
     * Custom validation messages.
     */
    public array $customMessages = [
        'to_url.required_if' => 'winter.redirect::lang.redirect.to_url_required_if',
        'cms_page.required_if' => 'winter.redirect::lang.redirect.cms_page_required_if',
        'static_page.required_if' => 'winter.redirect::lang.redirect.static_page_required_if',
        'is_regex' => 'winter.redirect::lang.redirect.invalid_regex',
    ];

    /**
     * Custom attribute names.
     */
    public array $attributeNames = [
        'to_url' => 'winter.redirect::lang.redirect.to_url',
        'to_scheme' => 'winter.redirect::lang.redirect.to_scheme',
        'from_url' => 'winter.redirect::lang.redirect.from_url',
        'from_scheme' => 'winter.redirect::lang.redirect.to_scheme',
        'match_type' => 'winter.redirect::lang.redirect.match_type',
        'target_type' => 'winter.redirect::lang.redirect.target_type',
        'cms_page' => 'winter.redirect::lang.redirect.target_type_cms_page',
        'static_page' => 'winter.redirect::lang.redirect.target_type_static_page',
        'status_code' => 'winter.redirect::lang.redirect.status_code',
        'from_date' => 'winter.redirect::lang.scheduling.from_date',
        'to_date' => 'winter.redirect::lang.scheduling.to_date',
        'sort_order' => 'winter.redirect::lang.redirect.sort_order',
        'requirements' => 'winter.redirect::lang.redirect.requirements',
        'last_used_at' => 'winter.redirect::lang.redirect.last_used_at',
    ];

    public $jsonable = [
        'requirements',
    ];

    public $hasMany = [
        'clients' => Client::class,
        'logs' => RedirectLog::class,
    ];

    public $belongsTo = [
        'category' => Category::class,
        'systemRequestLog' => [
            RequestLog::class,
            'key' => 'id',
            'otherKey' => 'winter_redirect_redirect_id',
        ],
    ];

    protected $guarded = [];

    protected $dates = [
        'from_date',
        'to_date',
        'last_used_at',
    ];

    protected $casts = [
        'ignore_query_parameters' => 'boolean',
        'ignore_case' => 'boolean',
        'ignore_trailing_slash' => 'boolean',
        'is_enabled' => 'boolean',
        'test_lab' => 'boolean',
        'system' => 'boolean',
    ];

    protected static function makeValidator(
        array $data,
        array $rules,
        array $customMessages = [],
        array $attributeNames = []
    ): Validator {
        $validator = self::traitMakeValidator($data, $rules, $customMessages, $attributeNames);

        $validator->sometimes('to_url', 'required', static function (Fluent $request): bool {
            return in_array($request->get('status_code'), ['301', '302', '303'], true)
                && $request->get('target_type') === self::TARGET_TYPE_PATH_URL;
        });

        $validator->sometimes('cms_page', 'required', static function (Fluent $request): bool {
            return in_array($request->get('status_code'), ['301', '302', '303'], true)
                && $request->get('target_type') === self::TARGET_TYPE_CMS_PAGE;
        });

        $validator->sometimes('static_page', 'required', static function (Fluent $request): bool {
            return in_array($request->get('status_code'), ['301', '302', '303'], true)
                && $request->get('target_type') === self::TARGET_TYPE_STATIC_PAGE;
        });

        $validator->sometimes('from_url', 'is_regex', static function (Fluent $request): bool {
            return $request->get('match_type') === self::TYPE_REGEX;
        });

        return $validator;
    }

    public function scopeEnabled(Builder $builder): Builder
    {
        return $builder->where('is_enabled', '=', true);
    }

    public function scopeTestLabEnabled(Builder $builder): Builder
    {
        return $builder->where('test_lab', '=', true);
    }

    public function isMatchTypeExact(): bool
    {
        return $this->attributes['match_type'] === self::TYPE_EXACT;
    }

    public function isMatchTypePlaceholders(): bool
    {
        return $this->attributes['match_type'] === self::TYPE_PLACEHOLDERS;
    }

    public function isMatchTypeRegex(): bool
    {
        return $this->attributes['match_type'] === self::TYPE_REGEX;
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function setSortableOrder($itemIds, array $itemOrders = null): void
    {
        $itemIds = array_map(static function ($itemId) {
            return (int) $itemId;
        }, Arr::wrap($itemIds));

        /** @var Dispatcher $dispatcher */
        $dispatcher = resolve(Dispatcher::class);
        $dispatcher->dispatch('winter.redirect.changed', ['redirectIds' => $itemIds]);

        $this->traitSetSortableOrder($itemIds, $itemOrders);
    }

    public function setFromUrlAttribute($value): void
    {
        $this->attributes['from_url'] = urldecode((string) $value);
    }

    public function setSortOrderAttribute($value): void
    {
        $this->attributes['sort_order'] = (int) $value;
    }

    public function getFromDateAttribute($value): ?Carbon
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return new Carbon($value);
    }

    public function getToDateAttribute($value): ?Carbon
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return new Carbon($value);
    }

    public function getMatchTypeOptions(): array
    {
        $options = [];

        foreach (self::$types as $value) {
            $options[$value] = e(trans("winter.redirect::lang.redirect.$value"));
        }

        return $options;
    }

    public function getTargetTypeOptions(): array
    {
        return OptionHelper::getTargetTypeOptions((int) $this->getAttribute('status_code'));
    }

    public function getCmsPageOptions(): array
    {
        return OptionHelper::getCmsPageOptions();
    }

    public function getStaticPageOptions(): array
    {
        return OptionHelper::getStaticPageOptions();
    }

    public function getCategoryOptions(): array
    {
        return OptionHelper::getCategoryOptions();
    }

    public function filterMatchTypeOptions(): array
    {
        $options = [];

        foreach (self::$types as $value) {
            $options[$value] = e(trans(sprintf('winter.redirect::lang.redirect.%s', $value)));
        }

        return $options;
    }

    public function filterTargetTypeOptions(): array
    {
        $options = [];

        foreach (self::$targetTypes as $value) {
            $options[$value] = e(trans(sprintf('winter.redirect::lang.redirect.target_type_%s', $value)));
        }

        return $options;
    }

    public function filterStatusCodeOptions(): array
    {
        $options = [];

        foreach (self::$statusCodes as $value => $message) {
            $options[$value] = e(trans(sprintf('winter.redirect::lang.redirect.%s', $message)));
        }

        return $options;
    }

    /**
     * Triggered before the model is saved, either created or updated.
     * Make sure target fields are correctly set after saving.
     *
     * @throws Exception
     */
    public function beforeSave(): void
    {
        parent::beforeSave();

        switch ($this->getAttribute('target_type')) {
            case self::TARGET_TYPE_NONE:
                $this->setAttribute('to_url', null);
                $this->setAttribute('cms_page', null);
                $this->setAttribute('static_page', null);
                $this->setAttribute('to_scheme', self::SCHEME_AUTO);
                break;

            case self::TARGET_TYPE_PATH_URL:
                $this->setAttribute('cms_page', null);
                $this->setAttribute('static_page', null);
                break;

            case self::TARGET_TYPE_CMS_PAGE:
                $this->setAttribute('to_url', null);
                $this->setAttribute('static_page', null);
                break;

            case self::TARGET_TYPE_STATIC_PAGE:
                $this->setAttribute('to_url', null);
                $this->setAttribute('cms_page', null);
                break;

        }
    }

    public function isActiveOnDate(Carbon $date): bool
    {
        if (
            $this->getAttribute('from_date') instanceof Carbon
            && $this->getAttribute('to_date') instanceof Carbon
        ) {
            return $date->between(
                $this->getAttribute('from_date'),
                $this->getAttribute('to_date')
            );
        }

        if (
            $this->getAttribute('from_date') instanceof Carbon
            && $this->getAttribute('to_date') === null
        ) {
            return $date->gte($this->getAttribute('from_date'));
        }

        if (
            $this->getAttribute('to_date') instanceof Carbon
            && $this->getAttribute('from_date') === null
        ) {
            return $date->lte($this->getAttribute('to_date'));
        }

        return true;
    }
}
