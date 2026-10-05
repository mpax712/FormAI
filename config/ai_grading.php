<?php

// Enable only after checking model capabilities, prices and quotas in the provider console.
// A group represents a shared provider/project quota (not an individual API key).
$profiles = [];
foreach (['economy', 'balanced', 'advanced'] as $profile) {
    $prefix = 'AI_'.strtoupper($profile);
    $destinations = [];
    foreach (['openrouter', 'gemini', 'openai'] as $provider) {
        $p = $prefix.'_'.strtoupper($provider);
        $destinations[] = [
            'provider' => $provider,
            'enabled' => (bool) env($p.'_ENABLED', false),
            'model' => env($p.'_MODEL'),
            'effort' => env($p.'_EFFORT'),
            'supported_efforts' => array_filter(explode(',', (string) env($p.'_SUPPORTED_EFFORTS', ''))),
            'structured_output' => (bool) env($p.'_STRUCTURED_OUTPUT', false),
            ...($provider === 'openrouter' ? ['response_format' => env($p.'_RESPONSE_FORMAT', env('OPENROUTER_RESPONSE_FORMAT', 'json_object'))] : []),
            'max_output_tokens' => (int) env($p.'_MAX_OUTPUT_TOKENS', 0),
            'input_price' => env($p.'_INPUT_USD_PER_MTOK'),
            'output_price' => env($p.'_OUTPUT_USD_PER_MTOK'),
            'quota_group' => env($p.'_QUOTA_GROUP', $provider),
        ];
    }
    $profiles[$profile] = ['cost_ceiling_usd' => env($prefix.'_COST_CEILING_USD'), 'destinations' => $destinations];
}
$quotas = [];
foreach (['openrouter', 'gemini', 'openai'] as $provider) {
    $prefix = 'AI_'.strtoupper($provider);
    $quotas[$provider] = [
        'rpm' => (int) env($prefix.'_RPM', 0), 'rph' => (int) env($prefix.'_RPH', 0),
        'rpd' => (int) env($prefix.'_RPD', 0), 'tpm' => (int) env($prefix.'_TPM', 0),
        'concurrency' => (int) env($prefix.'_CONCURRENCY', 1),
    ];
}

return [
    'profiles' => $profiles, 'quotas' => $quotas,
    'cache_store' => 'file', 'margin' => 0.8,
    'queue_timeout_seconds' => max(60, (int) env('AI_QUEUE_TIMEOUT_SECONDS', 86400)),
    'retry_cooldown_seconds' => max(60, (int) env('AI_RETRY_COOLDOWN_SECONDS', 300)),
    'max_calls' => 4, 'http_timeout_seconds' => 60, 'lease_seconds' => 100,
];
