<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AuditSensitiveAccess;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\RequestContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::get('/health', HealthController::class)->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Dev Tunnels terminate HTTPS before forwarding to the local PHP server.
        // Trust this only when explicitly enabled in the environment.
        $trustedProxies = env('TRUSTED_PROXIES');
        if (is_string($trustedProxies) && $trustedProxies !== '') {
            $middleware->trustProxies($trustedProxies);
        }
        $middleware->append(RequestContext::class);
        $middleware->append(AddSecurityHeaders::class);
        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'role' => EnsureRole::class,
            'audit.sensitive' => AuditSensitiveAccess::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();
    })
    ->create();
