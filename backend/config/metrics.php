<?php

return [
    /*
     | Master switch. When false, a no-op recorder is used and /metrics returns
     | an empty exposition — handy for environments that don't scrape.
     */
    'enabled' => env('METRICS_ENABLED', true),

    /*
     | Metric storage backend (promphp):
     |   'memory' — per-process, resets on restart. Fine for host dev + tests
     |              (single `artisan serve` / test process).
     |   'redis'  — shared across php-fpm workers and the api + clicks-worker
     |              containers, so one /metrics scrape sees everything. Uses the
     |              promphp *Predis* adapter (pure PHP — no phpredis extension),
     |              so no Dockerfile change. Compose sets this; host defaults to
     |              'memory' for a zero-dependency test run. See ADR-0004.
     */
    'storage' => env('METRICS_STORAGE', 'memory'),

    'namespace' => env('METRICS_NAMESPACE', 'linkforge'),

    'redis' => [
        'host' => env('METRICS_REDIS_HOST', env('REDIS_HOST', '127.0.0.1')),
        'port' => (int) env('METRICS_REDIS_PORT', env('REDIS_PORT', 6379)),
        'password' => env('METRICS_REDIS_PASSWORD', env('REDIS_PASSWORD')) ?: null,
        'database' => (int) env('METRICS_REDIS_DB', 2),
        'timeout' => 0.5,
    ],
];
