<?php

namespace App\Infrastructure\AI;

use RuntimeException;

class AiProviderConfiguration
{
    public function provider(): string
    {
        $provider = (string) config('formai.ai_provider', 'gemini');
        if (! in_array($provider, ['gemini', 'openai'], true)) {
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
        return $this->key() !== null;
    }

    public function keyEnvironmentName(): string
    {
        return $this->provider() === 'gemini' ? 'GEMINI_API_KEY' : 'OPENAI_API_KEY';
    }
}
