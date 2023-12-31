<?php

namespace Vanilla\Laravel\Http;

use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Log;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware to apply a log context about the current request.
 */
class RequestContextLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        /** @var LogManager $sharedContext */
        $logManager = app()->get(LogManager::class);
        $existingContext = method_exists($logManager, "sharedContext") ? Log::sharedContext() : [];
        Log::withContext(
            array_merge_recursive($existingContext, [
                "tags" => ["webRequest"],
                "request" => [
                    "hostname" => $request->getHost(),
                    "method" => $request->getMethod(),
                    "path" => $request->getPathInfo(),
                    "protocol" => $request->getScheme(),
                    "url" => $request->getUri(),
                    "clientIP" => $request->getClientIp(),
                ],
            ]),
        );

        return $next($request);
    }
}
