<?php

namespace App\Http\Middleware;

use App\Support\HttpMetrics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordHttpMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('metrics_started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->is('metrics')) {
            return;
        }

        $startedAt = (float) $request->attributes->get('metrics_started_at', microtime(true));
        $duration = max(0, microtime(true) - $startedAt);
        $route = $request->route()?->uri() ?? $request->path();

        HttpMetrics::record(
            $request->method(),
            '/'.ltrim($route, '/'),
            $response->getStatusCode(),
            $duration
        );
    }
}

