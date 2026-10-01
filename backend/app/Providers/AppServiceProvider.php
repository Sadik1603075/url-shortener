<?php

namespace App\Providers;

use App\Cache\Contracts\ShortUrlCacheInterface;
use App\Cache\RedisShortUrlCache;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Metrics\Metrics;
use App\Support\Contracts\ShortCodeCounterInterface;
use App\Support\DatabaseShortCodeCounter;
use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Predis;

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

        // Metrics registry (ADR-0004): Redis-backed (predis) storage shares
        // counters across workers/containers; otherwise per-process in-memory.
        $this->app->singleton(CollectorRegistry::class, function () {
            $storage = config('metrics.storage') === 'redis'
                ? new Predis([
                    'scheme' => 'tcp',
                    'host' => config('metrics.redis.host'),
                    'port' => config('metrics.redis.port'),
                    'password' => config('metrics.redis.password'),
                    'database' => config('metrics.redis.database'),
                    'timeout' => config('metrics.redis.timeout', 0.5),
                ])
                : new InMemory;

            return new CollectorRegistry($storage, false);
        });

        $this->app->singleton(Metrics::class, fn ($app) => new Metrics(
            $app->make(CollectorRegistry::class),
            (string) config('metrics.namespace', 'linkforge'),
            (bool) config('metrics.enabled', true),
        ));
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
