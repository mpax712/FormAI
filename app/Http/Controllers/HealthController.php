<?php

namespace App\Http\Controllers;

use App\Infrastructure\Observability\Models\SystemHeartbeat;
use App\Infrastructure\AI\AiProviderConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(AiProviderConfiguration $configuration): JsonResponse
    {
        try {
            DB::select('select 1');
            $heartbeat = SystemHeartbeat::query()->where('name', 'scheduler')->first();
            $queueLag = (int) (DB::table('jobs')->min('available_at') ? now()->timestamp - DB::table('jobs')->min('available_at') : 0);
            $cronHealthy = $heartbeat?->last_seen_at?->greaterThan(now()->subMinutes(3)) ?? false;
            $healthy = $cronHealthy && $queueLag <= config('formai.queue_lag_alert_seconds');
            $aiConfigured = $configuration->isConfigured();

            return response()->json([
                'status' => $healthy ? 'ok' : 'degraded',
                'database' => 'ok',
                'scheduler' => $cronHealthy ? 'ok' : 'stale',
                'queue_lag_seconds' => max(0, $queueLag),
                'ai' => $aiConfigured ? 'configured' : 'manual_only',
                'ai_provider' => $configuration->provider(),
            ], $healthy ? 200 : 503);
        } catch (Throwable) {
            return response()->json(['status' => 'down', 'database' => 'error'], 503);
        }
    }
}
