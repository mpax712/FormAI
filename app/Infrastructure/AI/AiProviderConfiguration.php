<?php

namespace App\Infrastructure\AI;

use App\Infrastructure\Observability\Models\SystemHeartbeat;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AiProviderConfiguration
{
    public function provider(): string
    {
        $provider = (string) config('formai.ai_provider', 'gemini');
        if (! in_array($provider, ['gemini', 'openai', 'openrouter'], true)) {
            throw new RuntimeException("Provedor de IA não suportado: {$provider}.");
        }

        return $provider;
    }

    public function key(): ?string
    {
        $key = config('services.'.$this->provider().'.key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function model(): string
    {
        return (string) config('services.'.$this->provider().'.model');
    }

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key')) || filled(config('services.openai.key')) || filled(config('services.openrouter.key'));
    }

    public function keyEnvironmentName(): string
    {
        return strtoupper($this->provider()).'_API_KEY';
    }

    public function runtimeWarning(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $heartbeat = SystemHeartbeat::query()->where('name', 'scheduler')->first();
        if (! ($heartbeat?->last_seen_at?->greaterThan(now()->subMinutes(3)) ?? false)) {
            return 'A IA está configurada, mas o agendador não está ativo. Inicie o ambiente com composer dev para processar as correções.';
        }

        $availableAt = DB::table('jobs')->min('available_at');
        $lag = $availableAt ? max(0, now()->timestamp - (int) $availableAt) : 0;
        if ($lag > (int) config('formai.queue_lag_alert_seconds')) {
            return "A fila de correção está atrasada há {$lag} segundos. Verifique o worker antes de solicitar novas correções.";
        }

        return null;
    }
}
