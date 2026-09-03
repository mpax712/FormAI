<?php

namespace App\Infrastructure\AI;

use App\Application\DTOs\GradingRequest;
use App\Application\DTOs\GradingResult;
use RuntimeException;

class GradingResponseMapper
{
    public function map(array $data, GradingRequest $request, ?int $inputTokens, ?int $outputTokens): GradingResult
    {
        $score = (float) ($data['score'] ?? -1);
        if ($score < 0 || $score > $request->maximumScore) {
            throw new RuntimeException('A IA retornou uma pontuação fora dos limites.');
        }

        $criterionScores = collect($data['criterion_scores'] ?? []);
        $criterionTotal = 0.0;
        foreach ($request->effectiveRubric() as $criterion) {
            $returned = $criterionScores->firstWhere('criterion', $criterion['label'] ?? null);
            $criterionScore = (float) ($returned['score'] ?? -1);
            $criterionMaximum = $request->maximumScore * (float) ($criterion['weight'] ?? 0);
            if ($criterionScore < 0 || $criterionScore > $criterionMaximum + 0.001) {
                throw new RuntimeException('A IA retornou pontuação inválida para um critério.');
            }
            $criterionTotal += $criterionScore;
        }
        if (abs($criterionTotal - $score) > 0.02) {
            throw new RuntimeException('A soma dos critérios da IA não corresponde ao total.');
        }

        return new GradingResult(
            $score,
            $criterionScores->values()->all(),
            $data['evidence'] ?? [],
            (string) ($data['feedback'] ?? ''),
            (float) ($data['confidence'] ?? 0),
            $data['warnings'] ?? [],
            $inputTokens,
            $outputTokens,
        );
    }

    public function schema(float $maximumScore): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'properties' => [
                'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => $maximumScore],
                'criterion_scores' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                    'criterion' => ['type' => 'string'], 'score' => ['type' => 'number'], 'justification' => ['type' => 'string'],
                ], 'required' => ['criterion', 'score', 'justification']]],
                'evidence' => ['type' => 'array', 'items' => ['type' => 'string']],
                'feedback' => ['type' => 'string'],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['score', 'criterion_scores', 'evidence', 'feedback', 'confidence', 'warnings'],
        ];
    }
}
