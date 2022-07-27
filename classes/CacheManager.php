<?php

declare(strict_types=1);

namespace Winter\Redirect\Classes;

use Config;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;
use Throwable;
use Winter\Redirect\Classes\Contracts\CacheManagerInterface;
use Winter\Redirect\Classes\Contracts\PublishManagerInterface;
use Winter\Redirect\Models\Settings;

final class CacheManager implements CacheManagerInterface
{
    private const CACHE_TAG = 'winter_redirect';
    private const CACHE_TAG_RULES = 'winter_redirect_rules';
    private const CACHE_TAG_MATCHES = 'winter_redirect_matches';

    private Repository $cache;
    private LoggerInterface $log;

    public function __construct(Repository $cache, LoggerInterface $log)
    {
        $this->cache = $cache;
        $this->log = $log;
    }

    public function get(string $key)
    {
        return $this->cache->get(self::CACHE_TAG_MATCHES . '.' . $key);
    }

    public function forget(string $key): bool
    {
        return $this->cache->forget(self::CACHE_TAG_MATCHES . '.'  . $key);
    }

    public function has(string $key): bool
    {
        return $this->cache->has(self::CACHE_TAG_MATCHES . '.' . $key);
    }

    public function cacheKey(string $requestPath, string $scheme): string
    {
        // Most caching backend have no limits on key lengths.
        // But to be sure I chose to MD5 hash the cache key.
        return md5($requestPath . $scheme);
    }

    public function flush(): void
    {
        foreach ([self::CACHE_TAG, self::CACHE_TAG_RULES, self::CACHE_TAG_MATCHES] as $key) {
            $this->cache->forget($key);
        }

        if ((bool) config('winter.redirect::log_redirect_changes', false) === true) {
            $this->log->info('Winter.Redirect: Redirect cache has been flushed.');
        }
    }

    public function putRedirectRules(array $redirectRules): void
    {
        $this->cache->forever(self::CACHE_TAG_RULES . '.rules', $redirectRules);
    }

    public function getRedirectRules(): array
    {
        if (!$this->cache->has(self::CACHE_TAG_RULES . '.rules')) {
            $publishManager = resolve(PublishManagerInterface::class);
            $publishManager->publish();
        }

        $data = $this->cache->get(self::CACHE_TAG_RULES . '.rules', []);

        if (is_array($data)) {
            return $data;
        }

        return [];
    }

    public function putMatch(string $cacheKey, ?RedirectRule $matchedRule = null): ?RedirectRule
    {
        if ($matchedRule === null) {
            $this->cache->forever(self::CACHE_TAG_MATCHES . '.' . $cacheKey, false);

            return null;
        }

        $matchedRuleToDate = $matchedRule->getToDate();

        if ($matchedRuleToDate instanceof Carbon) {
            $minutes = $matchedRuleToDate->diffInMinutes(Carbon::now());

            $this->cache->put(self::CACHE_TAG_MATCHES . '.' . $cacheKey, $matchedRule, $minutes);
        } else {
            $this->cache->forever(self::CACHE_TAG_MATCHES . '.' . $cacheKey, $matchedRule);
        }

        return $matchedRule;
    }

    public function cachingEnabledAndSupported(): bool
    {
        return Settings::isCachingEnabled() && !in_array(Config::get('cache.default'), ['file', 'database']);
    }

    public function cachingEnabledButNotSupported(): bool
    {
        return Settings::isCachingEnabled() && in_array(Config::get('cache.default'), ['file', 'database']);
    }
}
