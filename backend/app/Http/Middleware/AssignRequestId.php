<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Tags each request with a correlation id and pushes it (plus the actor) into
 * the log context, so structured (JSON) logs can be correlated per request.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();
        $request->headers->set('X-Request-Id', $requestId);

        // Resolve via the sanctum (bearer-token) guard explicitly: the default
        // web guard would do session work on every request (incl. the redirect
        // hot path) and return null for token-authenticated admins anyway.
        $actor = null;
        try {
            $actor = $request->user('sanctum')?->getAuthIdentifier();
        } catch (Throwable) {
            // no resolvable user — leave actor null
        }

        Log::withContext([
            'request_id' => $requestId,
            'actor' => $actor,
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
