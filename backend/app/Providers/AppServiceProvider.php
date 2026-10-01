<?php

namespace App\Providers;

use App\Cache\Contracts\ShortUrlCacheInterface;
use App\Cache\RedisShortUrlCache;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Support\Contracts\ShortCodeCounterInterface;
use App\Support\DatabaseShortCodeCounter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the cache abstraction so it can be injected anywhere
        $this->app->bind(
            ShortUrlCacheInterface::class,
            RedisShortUrlCache::class,
        );

        // Short-code counter source (ADR-0001)
        $this->app->bind(
            ShortCodeCounterInterface::class,
            DatabaseShortCodeCounter::class,
        );
    }

    public function boot(): void
    {
        // Register the "admin" route middleware alias
        $this->app['router']->aliasMiddleware(
            'admin',
            EnsureUserIsAdmin::class,
        );
    }
}
