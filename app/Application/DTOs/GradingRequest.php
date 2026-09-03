<?php

namespace App\Application\DTOs;

final readonly class GradingRequest
{
    public function __construct(
        public string $question,
        public string $expectedAnswer,
        public array $rubric,
        public string $studentAnswer,
        public string $teacherInstruction,
        public float $maximumScore,
        public int $promptVersion,
        public string $locale,
        public string $idempotencyKey,
        public string $safetyIdentifier,
    ) {}

    public function effectiveRubric(): array
    {
        if ($this->rubric !== []) {
            return $this->rubric;
        }

        return [[
            'label' => 'Qualidade geral da resposta',
            'description' => 'Avalie a correção, a relevância, a clareza e a completude da resposta em relação à pergunta.',
            'weight' => 1.0,
        ]];
    }
}
