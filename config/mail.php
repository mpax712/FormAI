<?php

return [
    'default' => env('MAIL_MAILER', 'smtp'),
    'mailers' => [
        'smtp' => ['transport' => 'smtp', 'scheme' => env('MAIL_SCHEME'), 'url' => env('MAIL_URL'), 'host' => env('MAIL_HOST', '127.0.0.1'), 'port' => env('MAIL_PORT', 2525), 'username' => env('MAIL_USERNAME'), 'password' => env('MAIL_PASSWORD'), 'timeout' => 10, 'local_domain' => env('MAIL_EHLO_DOMAIN')],
        'log' => ['transport' => 'log', 'channel' => env('MAIL_LOG_CHANNEL')],
        'array' => ['transport' => 'array'],
    ],
    'from' => ['address' => env('MAIL_FROM_ADDRESS', 'noreply@formai.local'), 'name' => env('MAIL_FROM_NAME', 'FormAI')],
];
