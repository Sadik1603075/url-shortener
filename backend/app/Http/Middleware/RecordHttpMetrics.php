<?php

namespace App\Http\Middleware;

use App\Metrics\Metrics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordHttpMetrics
{
    public function __construct(
        private readonly Metrics $metrics,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        try {
            $route = $request->route();
            // Use the route pattern (not the concrete path) to keep label cardinality bounded.
            $label = $route ? ($route->getName() ?: $route->uri()) : 'unmatched';

            $this->metrics->observeHttpRequest(
                $request->getMethod(),
                $label,
                $response->getStatusCode(),
                microtime(true) - $start,
            );
        } catch (Throwable) {
            // metrics are best-effort
        }

        return $response;
    }
}
