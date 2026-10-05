<?php

return [
    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'model' => env('OPENROUTER_GRADING_MODEL', 'stealth/space-bunny-alpha'),
        'response_format' => env('OPENROUTER_RESPONSE_FORMAT', 'json_object'),
        'connect_timeout' => (int) env('OPENROUTER_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 60),
        'max_output_tokens' => (int) env('OPENROUTER_MAX_OUTPUT_TOKENS', 8192),
        'temperature' => (float) env('OPENROUTER_TEMPERATURE', 0.2),
        'input_usd_per_mtok' => (float) env('OPENROUTER_INPUT_USD_PER_MTOK', 0),
        'output_usd_per_mtok' => (float) env('OPENROUTER_OUTPUT_USD_PER_MTOK', 0),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'model' => env('GEMINI_GRADING_MODEL', 'gemini-3.7-flash'),
        'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 1200),
        'temperature' => (float) env('GEMINI_TEMPERATURE', 0.2),
        'thinking_level' => env('GEMINI_THINKING_LEVEL', 'low'),
        'input_usd_per_mtok' => (float) env('GEMINI_INPUT_USD_PER_MTOK', 0),
        'output_usd_per_mtok' => (float) env('GEMINI_OUTPUT_USD_PER_MTOK', 0),
    ],

    // Provedor OpenAI preservado como alternativa. Para reativar, use AI_PROVIDER=openai.
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_GRADING_MODEL', 'gpt-5.6-terra'),
        'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 45),
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 1200),
        'input_usd_per_mtok' => (float) env('OPENAI_INPUT_USD_PER_MTOK', 2.00),
        'output_usd_per_mtok' => (float) env('OPENAI_OUTPUT_USD_PER_MTOK', 12.00),
    ],
];
