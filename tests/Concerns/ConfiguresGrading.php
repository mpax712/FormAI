<?php

namespace Tests\Concerns;

trait ConfiguresGrading
{
    protected function configureGrading(): void
    {
        config(['ai_grading.cache_store' => 'array', 'services.gemini.key' => 'fake', 'services.openai.key' => 'fake',
            'services.gemini.base_url' => 'https://gemini.test/v1beta', 'services.openai.base_url' => 'https://openai.test/v1']);
        foreach (['gemini', 'openai'] as $provider) {
            config(["ai_grading.quotas.$provider" => ['rpm' => 100, 'rph' => 1000, 'rpd' => 10000, 'tpm' => 1000000, 'concurrency' => 1]]);
        }
        foreach (['economy', 'balanced', 'advanced'] as $profile) {
            config(["ai_grading.profiles.$profile" => ['cost_ceiling_usd' => 1, 'destinations' => array_map(fn ($provider) => [
                'provider' => $provider, 'enabled' => true, 'model' => 'test-'.$profile, 'effort' => 'low',
                'supported_efforts' => ['low'], 'structured_output' => true, 'max_output_tokens' => 5000,
                'input_price' => 0.1, 'output_price' => 0.2, 'quota_group' => $provider,
            ], ['gemini', 'openai'])]]);
        }
    }
}
