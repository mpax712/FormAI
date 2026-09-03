<?php

return [
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'model' => env('GEMINI_GRADING_MODEL', 'gemini-3.7-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 45),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 1200),
        'temperature' => (float) env('GEMINI_TEMPERATURE', 0.2),
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
