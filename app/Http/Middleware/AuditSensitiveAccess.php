<?php

namespace App\Http\Middleware;

use App\Domain\Administration\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditSensitiveAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user()?->isAdmin()) {
            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'event' => 'admin.sensitive_access',
                'route' => $request->route()?->getName() ?? $request->path(),
                'ip_address' => $request->ip(),
                'correlation_id' => $request->attributes->get('correlation_id'),
                'metadata' => ['method' => $request->method(), 'status' => $response->getStatusCode()],
            ]);
        }

        return $response;
    }
}
