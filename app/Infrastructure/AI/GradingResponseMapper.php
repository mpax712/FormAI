<?php

namespace App\Infrastructure\AI;

use App\Application\DTOs\GradingRequest;
use App\Application\DTOs\GradingResult;
use App\Infrastructure\AI\Exceptions\PermanentAiException;
use RuntimeException;

class GradingResponseMapper
{
    public function map(array $data, GradingRequest $request, ?int $inputTokens, ?int $outputTokens): GradingResult
    {
        $numeric = fn ($value) => (is_int($value) || is_float($value)) && is_finite((float) $value);
        if (! $numeric($data['score'] ?? null) || ! $numeric($data['confidence'] ?? null)
            || ! is_string($data['feedback'] ?? null) || ! is_array($data['criterion_scores'] ?? null)
            || ! is_array($data['evidence'] ?? null) || ! is_array($data['warnings'] ?? null)
            || count($data['criterion_scores']) !== count($request->effectiveRubric())) {
            throw new RuntimeException('Estrutura de resposta inválida.');
        }
        foreach (array_merge($data['evidence'], $data['warnings']) as $text) {
            if (! is_string($text)) {
                throw new RuntimeException('Evidências ou avisos inválidos.');
            }
        }
        foreach ($data['criterion_scores'] as $criterion) {
            if (! is_array($criterion) || ! is_string($criterion['criterion'] ?? null)
                || ! $numeric($criterion['score'] ?? null) || ! is_string($criterion['justification'] ?? null)) {
                throw new RuntimeException('Critério inválido.');
            }
        }
        if (count(array_unique(array_column($data['criterion_scores'], 'criterion'))) !== count($data['criterion_scores'])) {
            throw new RuntimeException('Critérios duplicados.');
        }
        $score = (float) ($data['score'] ?? -1);
        if ($request->systemInstruction !== null && (! is_string($data['teacher_feedback'] ?? null) || trim($data['teacher_feedback']) === '')) {
            throw new PermanentAiException('A IA não retornou a análise privada do professor.');
        }
        if ($score < 0 || $score > $request->maximumScore) {
            throw new RuntimeException('A IA retornou uma pontuação fora dos limites.');
        }

        $criterionScores = collect($data['criterion_scores'] ?? []);
        $criterionTotal = 0.0;
        foreach ($request->effectiveRubric() as $criterion) {
            $returned = $criterionScores->firstWhere('criterion', $criterion['label'] ?? null);
            $criterionScore = (float) ($returned['score'] ?? -1);
            $criterionMaximum = (float) ($criterion['weight'] ?? 0);
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
            isset($data['teacher_feedback']) ? $this->limitTeacherFeedback($data['teacher_feedback'], $request->feedbackDetail) : null,
        );
    }

    private function limitTeacherFeedback(string $feedback, string $detail): string
    {
        $limit = match ($detail) {
            'short' => 30,
            'detailed' => 350,
            default => 150,
        };
        $words = preg_split('/\s+/u', trim($feedback), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) <= $limit) {
            return trim($feedback);
        }

        return implode(' ', array_slice($words, 0, $limit)).'…';
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
                'teacher_feedback' => ['type' => 'string'],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['score', 'criterion_scores', 'evidence', 'feedback', 'teacher_feedback', 'confidence', 'warnings'],
        ];
    }
}
