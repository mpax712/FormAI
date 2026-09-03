<?php

namespace App\Domain\Grading\Contracts;

use App\Application\DTOs\GradingRequest;
use App\Application\DTOs\GradingResult;

interface AiGradingProvider
{
    public function grade(GradingRequest $request): GradingResult;
}
