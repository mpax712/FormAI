<?php

namespace App\Providers;

use App\Domain\Grading\Contracts\AiGradingProvider;
use App\Domain\Identity\Models\User;
use App\Infrastructure\AI\OpenAiGradingProvider;
use App\Infrastructure\AI\GeminiGradingProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiGradingProvider::class, function () {
            return match (config('formai.ai_provider')) {
                'gemini' => new GeminiGradingProvider(),
                'openai' => new OpenAiGradingProvider(),
                default => throw new \RuntimeException('Provedor de IA nao configurado.'),
            };
        });
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('class-code', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('invites', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id));
        RateLimiter::for('autosave', fn (Request $request) => Limit::perMinute(60)->by((string) $request->user()?->id));
        RateLimiter::for('ai', fn (Request $request) => [Limit::perMinute(10)->by((string) $request->user()?->id), Limit::perHour(100)->by((string) $request->user()?->id)]);
    }
}
