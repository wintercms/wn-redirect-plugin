<?php

/** @noinspection EfferentObjectCouplingInspection */

declare(strict_types=1);

namespace Winter\Redirect\Classes;

use ApplicationException;
use Carbon\Carbon;
use Cms\Classes\CmsException;
use Cms\Classes\Controller;
use Cms\Classes\Router;
use Cms\Classes\Theme;
use Cms\Helpers\Cms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use RuntimeException;
use Symfony\Component\Routing;
use Throwable;
use Winter\Redirect\Classes\Contracts\CacheManagerInterface;
use Winter\Redirect\Classes\Contracts\RedirectConditionInterface;
use Winter\Redirect\Classes\Contracts\RedirectManagerInterface;
use Winter\Redirect\Classes\Exceptions;
use Winter\Redirect\Classes\Util\Str;
use Winter\Redirect\Models;
use Winter\Storm\Router\UrlGenerator;

final class RedirectManager implements RedirectManagerInterface
{
    /**
     * The redirect rules which this manager uses to perform matching.
     *
     * @var RedirectRule[]|null
     */
    private ?array $rules = null;

    /**
     * @var RedirectConditionInterface[]
     */
    private array $conditions = [];

    /**
     * The date for which the matching should be done.
     */
    private Carbon $matchDate;

    /**
     * Site base path.
     */
    private string $basePath;

    private RedirectManagerSettings $settings;
    private CacheManagerInterface $cacheManager;

    /**
     * HTTP 1.1 headers
     */
    private static array $headers = [
        301 => 'HTTP/1.1 301 Moved Permanently',
        302 => 'HTTP/1.1 302 Found',
        303 => 'HTTP/1.1 303 See Other',
        404 => 'HTTP/1.1 404 Not Found',
        410 => 'HTTP/1.1 410 Gone',
    ];

    private static array $schemes = [
        Models\Redirect::SCHEME_HTTP,
        Models\Redirect::SCHEME_HTTPS,
    ];

    public function __construct(Request $request, CacheManagerInterface $cacheManager)
    {
        $this->matchDate = Carbon::today();
        $this->basePath = $request->getBasePath();
        $this->settings = RedirectManagerSettings::createDefault();
        $this->cacheManager = $cacheManager;
    }

    public static function createWithRule(RedirectRule $rule): RedirectManagerInterface
    {
        $instance = new self(
            resolve(Request::class),
            resolve(CacheManagerInterface::class)
        );

        $instance->rules[] = $rule;

        return $instance;
    }

    /**
     * {@inheritDoc}
     * @throws Exceptions\InvalidScheme
     * @throws Exceptions\NoMatchForRequest
     * @throws Exceptions\UnableToLoadRules
     */
    public function match(string $requestPath, string $scheme): RedirectRule
    {
        if (!in_array($scheme, self::$schemes, true)) {
            throw Exceptions\InvalidScheme::withScheme($scheme);
        }

        $requestPath = urldecode($requestPath);

        $this->loadRedirectRules();

        foreach ((array) $this->rules as $rule) {
            try {
                return $this->matchesRule($rule, $requestPath, $scheme);
            } catch (Exceptions\NoMatchForRule $exception) {
                continue;
            }
        }

        throw Exceptions\NoMatchForRequest::withRequestPath($requestPath, $scheme);
    }

    public function matchCached(string $requestPath, string $scheme): ?RedirectRule
    {
        $cacheKey = $this->cacheManager->cacheKey($requestPath, $scheme);

        if ($this->cacheManager->has($cacheKey)) {
            $cachedItem = $this->cacheManager->get($cacheKey);

            // Verify the data from cache. In some cases a cache driver can not unserialize
            // (due to invalid php configuration) the cached data which causes this function to return invalid data.
            //
            // E.g. memcache:
            // - Memcached::get(): could not unserialize value, no igbinary support in ...
            // - Memcached::get(): could not unserialize value, no msgpack support in ...
            if ($cachedItem instanceof RedirectRule) {
                return $cachedItem;
            }
        }

        try {
            $matchedRule = $this->match($requestPath, $scheme);
        } catch (Exceptions\NoMatchForRequest | Exceptions\InvalidScheme | Exceptions\UnableToLoadRules $exception) {
            $matchedRule = null;
        }

        return $this->cacheManager->putMatch($cacheKey, $matchedRule);
    }

    /**
     * @throws CmsException
     */
    public function redirectWithRule(RedirectRule $rule, string $requestUri): void
    {
        if ($this->settings->isStatisticsEnabled()) {
            (new StatisticsHelper())->increaseHitsForRedirect($rule->getId());
        }

        $statusCode = $rule->getStatusCode();

        if ($statusCode === 404 || $statusCode === 410) {
            header(self::$headers[$statusCode], true, $statusCode);
            $this->addLogEntry($rule, $requestUri, '');
            exit(0);
        }

        $toUrl = $this->getLocation($rule);

        $targetIsEqual = $this->settings->isRelativePathsEnabled()
            ? $requestUri === $toUrl
            : (new Cms())->url($requestUri) === $toUrl;

        if (
            $toUrl === null
            || $toUrl === ''
            || $targetIsEqual // Prevent redirect loop
        ) {
            return;
        }

        $this->addLogEntry($rule, $requestUri, $toUrl);

        header(self::$headers[$statusCode], true, $statusCode);
        header('X-Redirect-By: Winter.Redirect');
        header('X-Redirect-Id: ' . $rule->getId());
        header('Cache-Control: no-store');
        header('Location: ' . $toUrl, true, $statusCode);

        exit(0);
    }

    /**
     * @throws CmsException
     */
    public function getLocation(RedirectRule $rule): ?string
    {
        $toUrl = null;

        // Determine the URL to redirect to.
        switch ($rule->getTargetType()) {
            case Models\Redirect::TARGET_TYPE_PATH_URL:
                $toUrl = $this->redirectToPathOrUrl($rule);

                // Check if $toUrl is a relative path, if so, we need to add the base path to it.
                if (
                    $toUrl[0] !== '/'
                    && strncmp($toUrl, 'http://', 7) !== 0
                    && strncmp($toUrl, 'https://', 8) !== 0
                ) {
                    $toUrl = $this->basePath . '/' . $toUrl;
                }

                if (strncmp($toUrl, '/', 1) === 0) {
                    $toUrl = $this->settings->isRelativePathsEnabled()
                        ? $toUrl
                        : (new Cms())->url($toUrl);
                }

                break;

            case Models\Redirect::TARGET_TYPE_CMS_PAGE:
                $toUrl = $this->redirectToCmsPage($rule);

                break;

            case Models\Redirect::TARGET_TYPE_STATIC_PAGE:
                try {
                    $toUrl = $this->redirectToStaticPage($rule);
                } catch (Throwable $throwable) {
                    // @ignoreException
                }

                break;
        }

        if (
            is_string($toUrl)
            && $rule->getToScheme() !== Models\Redirect::SCHEME_AUTO
            && (strncmp($toUrl, 'http://', 7) === 0 || strncmp($toUrl, 'https://', 8) === 0)
        ) {
            $toUrl = str_replace(['https://', 'http://'], $rule->getToScheme() . '://', $toUrl);
        }

        if ($rule->isForwardQueryParameters() && $params = \request()->query()) {
            $toUrl = UrlGenerator::buildUrl($toUrl, [
                'query' => $params
            ]);
        }

        return $toUrl;
    }

    public function getConditions(): array
    {
        return array_keys($this->conditions);
    }

    public function addCondition(string $conditionClass, int $priority): RedirectManagerInterface
    {
        $this->conditions[$conditionClass] = $priority;
        arsort($this->conditions);

        return $this;
    }

    public function setSettings(RedirectManagerSettings $settings): RedirectManagerInterface
    {
        $this->settings = $settings;

        return $this;
    }

    public function setBasePath(string $basePath): RedirectManager
    {
        $this->basePath = rtrim($basePath, '/');

        return $this;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function setMatchDate(Carbon $matchDate): RedirectManager
    {
        $this->matchDate = $matchDate;

        return $this;
    }

    private function redirectToPathOrUrl(RedirectRule $rule): string
    {
        if ($rule->isExactMatchType()) {
            return $rule->getToUrl();
        }

        /*
         * Handle preg_match matches.
         *
         * Example:
         *
         * Source Path: @/foo/(.*)?@
         * Target Path: /bar/{1}
         * Result: /foo/my-match -> /bar/my-match
         */
        $pregMatchMatches = $rule->getPregMatchMatches();

        if ($rule->isRegexMatchType() && count($pregMatchMatches) > 0) {
            $search = array_map(
                static fn($key): string => '{' . $key . '}',
                array_keys($pregMatchMatches)
            );

            return str_replace($search, $pregMatchMatches, $rule->getToUrl());
        }

        /*
         * Handle placeholder matches.
         */
        $placeholderMatches = $rule->getPlaceholderMatches();

        if (count($placeholderMatches) === 0) {
            return $rule->getToUrl();
        }

        return str_replace(
            array_keys($placeholderMatches),
            array_values($placeholderMatches),
            $rule->getToUrl()
        );
    }

    /**
     * @throws CmsException|ApplicationException
     */
    private function redirectToCmsPage(RedirectRule $rule): string
    {
        $parameters = [];

        // Strip curly braces from keys
        foreach ($rule->getPlaceholderMatches() as $placeholder => $value) {
            $parameters[str_replace(['{', '}'], '', (string) $placeholder)] = $value;
        }

        if ($this->settings->isRelativePathsEnabled()) {
            $router = new Router(Theme::getActiveTheme());

            return $router->findByFile(
                $rule->getCmsPage(),
                array_merge($router->getParameters(), $parameters)
            );
        }

        return (string) (new Controller(Theme::getActiveTheme()))
            ->pageUrl($rule->getCmsPage(), $parameters);
    }

    /**
     * @throws RuntimeException|ApplicationException
     */
    private function redirectToStaticPage(RedirectRule $rule): string
    {
        if (!class_exists('\Winter\Pages\Classes\Page')) {
            throw new RuntimeException('Cannot create URL to Winter Page: Plugin not installed.');
        }

        /** @var \Winter\Pages\Classes\Page $page */
        $page = \Winter\Pages\Classes\Page::loadCached(
            Theme::getActiveTheme(),
            $rule->getStaticPage()
        );

        if ($page === null) {
            throw new RuntimeException('Cannot create URL to Winter Page: Page not found.');
        }

        return $this->settings->isRelativePathsEnabled()
            ? (string) array_get($page->attributes, 'viewBag.url')
            : (string) \Winter\Pages\Classes\Page::url($rule->getStaticPage());
    }

    /**
     * @throws Exceptions\NoMatchForRule
     */
    private function matchesRule(RedirectRule $rule, string $requestPath, string $scheme): RedirectRule
    {
        if (!$this->matchesScheme($rule, $scheme)
            || !$this->matchesPeriod($rule)
        ) {
            throw Exceptions\NoMatchForRule::withRedirectRule($rule, $requestPath, $scheme);
        }

        // Strip query parameters from request path.
        if ($rule->isIgnoreQueryParameters()) {
            $parseResult = parse_url($requestPath, PHP_URL_PATH);

            if (is_string($parseResult)) {
                $requestPath = $parseResult;
            }
        }

        // Perform exact match if applicable.
        if ($rule->isExactMatchType()) {
            return $this->matchExact($rule, $requestPath);
        }

        // Perform placeholders match if applicable.
        if ($rule->isPlaceholdersMatchType()) {
            return $this->matchPlaceholders($rule, $requestPath);
        }

        // Perform regex match if applicable.
        if ($rule->isRegexMatchType()) {
            return $this->matchRegex($rule, $requestPath);
        }

        throw Exceptions\NoMatchForRule::withRedirectRule($rule, $requestPath, $scheme);
    }

    /**
     * @throws Exceptions\NoMatchForRule
     */
    private function matchExact(RedirectRule $rule, string $url): RedirectRule
    {
        $urlA = $rule->getFromUrl();
        $urlB = $url;

        if ($rule->isIgnoreTrailingSlash()) {
            $urlA = Str::removeTrailingSlash($urlA);
            $urlB = Str::removeTrailingSlash($urlB);
        }

        if ($rule->isIgnoreCase() && strcasecmp($urlA, $urlB) === 0) {
            return $rule;
        }

        if ($urlA === $urlB) {
            return $rule;
        }

        throw Exceptions\NoMatchForRule::withRedirectRule($rule, $url);
    }

    /**
     * @throws Exceptions\NoMatchForRule
     */
    private function matchPlaceholders(RedirectRule $rule, string $url): RedirectRule
    {
        $route = new Routing\Route($rule->getFromUrl());

        foreach ($rule->getRequirements() as $requirement) {
            try {
                $route->setRequirement(
                    str_replace(['{', '}'], '', $requirement['placeholder']),
                    $requirement['requirement']
                );
            } catch (Throwable $throwable) {
                // Catch empty requirement / placeholder
            }
        }

        $routeCollection = new Routing\RouteCollection();
        $routeCollection->add((string) $rule->getId(), $route);

        try {
            $matcher = new Routing\Matcher\UrlMatcher(
                $routeCollection,
                new Routing\RequestContext('/')
            );

            $match = $matcher->match($url);

            $items = array_except($match, '_route');

            foreach ($items as $key => $value) {
                $placeholder = '{' . $key . '}';
                $replacement = $this->findReplacementForPlaceholder($rule, $placeholder);
                $items[$placeholder] = $replacement ?? $value;
                unset($items[$key]);
            }

            $rule->setPlaceholderMatches($items);
        } catch (Throwable $throwable) {
            throw Exceptions\NoMatchForRule::withRedirectRule($rule, $url);
        }

        return $rule;
    }

    /**
     * @throws Exceptions\NoMatchForRule
     */
    private function matchRegex(RedirectRule $rule, string $url): RedirectRule
    {
        $pattern = $rule->getFromUrl();

        try {
            if (preg_match($pattern, $url, $matches) === 1) {
                return $rule->setPregMatchMatches($matches);
            }
        } catch (Throwable $throwable) {
            // @ignoreException
        }

        throw Exceptions\NoMatchForRule::withRedirectRule($rule, $url);
    }

    private function matchesPeriod(RedirectRule $rule): bool
    {
        if ($rule->getFromDate() instanceof Carbon && $rule->getToDate() instanceof Carbon) {
            return $this->matchDate->between($rule->getFromDate(), $rule->getToDate());
        }

        if ($rule->getFromDate() instanceof Carbon && $rule->getToDate() === null) {
            return $this->matchDate->gte($rule->getFromDate());
        }

        if ($rule->getToDate() instanceof Carbon && $rule->getFromDate() === null) {
            return $this->matchDate->lte($rule->getToDate());
        }

        return true;
    }

    private function matchesScheme(RedirectRule $rule, string $scheme): bool
    {
        if ($rule->getFromScheme() === Models\Redirect::SCHEME_AUTO) {
            return true;
        }

        return $rule->getFromScheme() === $scheme;
    }

    private function findReplacementForPlaceholder(RedirectRule $rule, string $placeholder): ?string
    {
        foreach ($rule->getRequirements() as $requirement) {
            if ($requirement['placeholder'] === $placeholder && !empty($requirement['replacement'])) {
                return (string) $requirement['replacement'];
            }
        }

        return null;
    }

    /**
     * @throws Exceptions\UnableToLoadRules
     */
    private function loadRedirectRules(): void
    {
        if ($this->rules !== null) {
            return;
        }

        if ($this->cacheManager->cachingEnabledAndSupported()) {
            $rules = $this->loadRulesFromCache();
        } else {
            $rules = $this->loadRulesFromFilesystem();
        }

        $this->rules = $rules;
    }

    /**
     * @throws Exceptions\UnableToLoadRules
     */
    private function loadRulesFromFilesystem(): array
    {
        $rulesPath = (string) config('winter.redirect::rules_path');

        if (!file_exists($rulesPath) && touch($rulesPath) === false) {
            throw Exceptions\RulesPathNotWritable::withPath($rulesPath);
        }

        if (!is_readable($rulesPath)) {
            throw Exceptions\RulesPathNotReadable::withPath($rulesPath);
        }

        try {
            $reader = Reader::createFromPath($rulesPath, 'r');
            $reader->setHeaderOffset(0);

            $results = $reader->getRecords();
        } catch (Throwable $throwable) {
            throw Exceptions\UnableToLoadRules::withMessage($throwable->getMessage(), $throwable);
        }

        $rules = [];

        foreach ($results as $row) {
            $rule = new RedirectRule($row);

            if ($this->matchesPeriod($rule)) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    private function loadRulesFromCache(): array
    {
        $results = $this->cacheManager->getRedirectRules();

        $rules = [];

        foreach ($results as $row) {
            $rule = new RedirectRule($row);

            if ($this->matchesPeriod($rule)) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    private function addLogEntry(RedirectRule $rule, string $requestUri, string $toUrl): void
    {
        if (!$this->settings->isLoggingEnabled()) {
            return;
        }

        try {
            $fromToHash = sha1($requestUri . $toUrl);

            Models\RedirectLog::query()->updateOrCreate([
                'redirect_id' => $rule->getId(),
                'from_to_hash' => $fromToHash,
            ], [
                'redirect_id' => $rule->getId(),
                'from_to_hash' => $fromToHash,
                'from_url' => $requestUri,
                'to_url' => $toUrl,
                'status_code' => $rule->getStatusCode(),
                'hits' => DB::raw('hits + 1'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $throwable) {
            // @ignoreException
        }
    }
}
