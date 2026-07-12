<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequestTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('local')) {
            return $next($request);
        }

        $startedAt = microtime(true);
        $bootstrapMs = defined('LARAVEL_START')
            ? round(($startedAt - LARAVEL_START) * 1000, 1)
            : null;

        $response = $next($request);

        $handlerMs = round((microtime(true) - $startedAt) * 1000, 1);
        $totalMs = defined('LARAVEL_START')
            ? round((microtime(true) - LARAVEL_START) * 1000, 1)
            : $handlerMs;

        Log::info('request.timing', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'bootstrap_ms' => $bootstrapMs,
            'handler_ms' => $handlerMs,
            'total_ms' => $totalMs,
        ]);

        return $response;
    }
}
