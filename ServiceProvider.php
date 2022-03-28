<?php

declare(strict_types=1);

namespace Winter\Redirect;

use Winter\Storm\Support\ServiceProvider as ServiceProviderBase;
use Winter\Redirect\Classes\CacheManager;
use Winter\Redirect\Classes\Contracts;
use Winter\Redirect\Classes\PublishManager;
use Winter\Redirect\Classes\RedirectManager;

final class ServiceProvider extends ServiceProviderBase
{
    public function register(): void
    {
        $this->app->bind(Contracts\RedirectManagerInterface::class, RedirectManager::class);
        $this->app->bind(Contracts\PublishManagerInterface::class, PublishManager::class);
        $this->app->bind(Contracts\CacheManagerInterface::class, CacheManager::class);

        $this->app->singleton(RedirectManager::class);
        $this->app->singleton(PublishManager::class);
        $this->app->singleton(CacheManager::class);
    }
}
