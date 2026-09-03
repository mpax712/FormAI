<?php

namespace App\Domain\Grading\Enums;

enum GradingRunStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case RetryableFailed = 'retryable_failed';
    case PermanentlyFailed = 'permanently_failed';
}
