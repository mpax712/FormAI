<?php

namespace App\Infrastructure\AI\Exceptions;

class ProviderFailure extends RetryableAiException
{
    public function __construct(public readonly string $kind, string $message, public readonly int $retryAfter = 0)
    {
        parent::__construct($message);
    }
}
