<?php

namespace App\Providers;

use App\Domain\Grading\Contracts\AiGradingProvider;
use App\Domain\Identity\Models\User;
use App\Infrastructure\AI\GeminiGradingProvider;
use App\Infrastructure\AI\OpenAiGradingProvider;
use App\Infrastructure\AI\OpenRouterGradingProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiGradingProvider::class, function () {
            return match (config('formai.ai_provider')) {
                'gemini' => new GeminiGradingProvider,
                'openai' => new OpenAiGradingProvider,
                'openrouter' => new OpenRouterGradingProvider,
                default => throw new \RuntimeException('Provedor de IA nao configurado.'),
            };
        });
    }

    public function boot(): void
    {
        $this->useFallbackDatabaseWhenNeeded();

        DevCommands::artisan('schedule:work', 'scheduler');
        DevCommands::artisan('queue:listen database --queue=ai,default --tries=2 --timeout=90', 'queue');
        DevCommands::except('vite');
        Paginator::useBootstrapFive();
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('email-code', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('email-code-resend', fn (Request $request) => Limit::perMinute(3)->by((string) $request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('class-code', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('invites', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id));
        RateLimiter::for('autosave', fn (Request $request) => Limit::perMinute(60)->by((string) $request->user()?->id));
        RateLimiter::for('ai', fn (Request $request) => [
            Limit::perMinute(3)->by('minute:'.(string) $request->user()?->id),
            Limit::perHour(20)->by('hour:'.(string) $request->user()?->id),
        ]);
    }

    private function useFallbackDatabaseWhenNeeded(): void
    {
        if ($this->app->runningUnitTests() || ! config('database.fallback.enabled')) {
            return;
        }

        $primaryConnection = (string) config('database.default');
        $fallbackConnection = (string) config('database.fallback.connection');

        if ($primaryConnection === '' || $fallbackConnection === '' || $primaryConnection === $fallbackConnection) {
            return;
        }

        if (! array_key_exists($fallbackConnection, config('database.connections', []))) {
            return;
        }

        try {
            DB::connection($primaryConnection)->getPdo();
        } catch (\Throwable $exception) {
            try {
                DB::connection($fallbackConnection)->getPdo();
            } catch (\Throwable $fallbackException) {
                DB::purge($primaryConnection);
                DB::purge($fallbackConnection);

                Log::error('Database primary and fallback connections are unavailable.', [
                    'primary' => $primaryConnection,
                    'fallback' => $fallbackConnection,
                    'primary_error' => $exception->getMessage(),
                    'fallback_error' => $fallbackException->getMessage(),
                ]);

                return;
            }

            DB::purge($primaryConnection);
            Config::set('database.default', $fallbackConnection);
            DB::setDefaultConnection($fallbackConnection);

            Log::warning('Database primary connection failed; using fallback connection.', [
                'primary' => $primaryConnection,
                'fallback' => $fallbackConnection,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
