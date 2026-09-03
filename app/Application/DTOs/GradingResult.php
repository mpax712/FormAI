<?php

namespace App\Application\DTOs;

use InvalidArgumentException;

final readonly class GradingResult
{
    public function __construct(
        public float $score,
        public array $criterionScores,
        public array $evidence,
        public string $feedback,
        public float $confidence,
        public array $warnings,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {
        if ($confidence < 0 || $confidence > 1) {
            throw new InvalidArgumentException('Confianca fora do intervalo permitido.');
        }
    }
}
