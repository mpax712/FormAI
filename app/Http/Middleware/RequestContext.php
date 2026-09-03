<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->headers->get('X-Request-ID');
        if (! is_string($id) || ! Str::isUuid($id)) {
            $id = (string) Str::uuid();
        }

        $request->attributes->set('correlation_id', $id);
        Log::withContext(['correlation_id' => $id, 'user_id' => $request->user()?->id]);
        $started = microtime(true);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $id);

        Log::info('http.request', [
            'method' => $request->method(),
            'route' => $request->route()?->getName() ?? $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round((microtime(true) - $started) * 1000, 2),
        ]);

        return $response;
    }
}
