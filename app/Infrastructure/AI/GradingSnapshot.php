<?php

namespace App\Infrastructure\AI;

use App\Application\DTOs\GradingRequest;
use App\Domain\Submissions\Models\Answer;

class GradingSnapshot
{
    public function capture(Answer $answer, array $options): array
    {
        $answer->loadMissing('activityQuestion.activity', 'submission');
        $question = $answer->activityQuestion;
        $snapshot = [
            'question' => $question->body, 'expectedAnswer' => (string) $question->expected_answer,
            'rubric' => $question->rubric_snapshot ?? [], 'studentAnswer' => (string) $answer->response_text,
            'teacherInstruction' => trim($question->activity->grading_instructions."\n".$question->teacher_instruction),
            'maximumScore' => (float) $question->max_score, 'promptVersion' => (int) config('formai.prompt_version'),
            'locale' => 'pt-BR', 'idempotencyKey' => '',
            'safetyIdentifier' => hash_hmac('sha256', 'student:'.$answer->submission->student_id, (string) config('app.key')),
            'feedbackDetail' => $options['feedback_detail'], 'intelligenceProfile' => $options['intelligence_profile'],
            'gradingStrictness' => $options['grading_strictness'],
        ];
        $snapshot['systemInstruction'] = app(PromptComposer::class)->systemPrompt(new GradingRequest(...$snapshot));
        $snapshot['composedInput'] = app(PromptComposer::class)->input(new GradingRequest(...$snapshot));
        $snapshot['responseSchema'] = app(GradingResponseMapper::class)->schema($snapshot['maximumScore']);

        return $snapshot;
    }

    public function inputBytes(array $snapshot): int
    {
        return strlen(app(PromptComposer::class)->input(new GradingRequest(...$snapshot))) + strlen($snapshot['systemInstruction']) + 2048;
    }
}
