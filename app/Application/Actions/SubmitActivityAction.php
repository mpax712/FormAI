<?php

namespace App\Application\Actions;

use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Submissions\Enums\SubmissionStatus;
use App\Domain\Submissions\Models\Submission;
use DomainException;
use Illuminate\Support\Facades\DB;

class SubmitActivityAction
{
    public function execute(Submission $submission): Submission
    {
        return DB::transaction(function () use ($submission) {
            $locked = Submission::query()->with(['activity.questions', 'answers'])->lockForUpdate()->findOrFail($submission->id);
            if ($locked->status !== SubmissionStatus::Draft) {
                throw new DomainException('A entrega ja foi finalizada.');
            }
            $hasExtension = $locked->reopened_until?->isFuture() ?? false;
            if (! in_array($locked->activity->status, [ActivityStatus::Published, ActivityStatus::Closed, ActivityStatus::Grading], true)
                || ($locked->activity->deadline_at && now()->greaterThan($locked->activity->deadline_at) && ! $hasExtension)) {
                throw new DomainException('O prazo desta atividade terminou.');
            }

            $answers = $locked->answers->keyBy('activity_question_id');
            foreach ($locked->activity->questions as $question) {
                $answer = $answers->get($question->id);
                $filled = $question->type === QuestionType::Essay ? filled($answer?->response_text) : filled($answer?->selected_option_key);
                if (! $filled) {
                    throw new DomainException('Responda todas as questoes antes de enviar.');
                }
            }

            $objectiveScore = $locked->activity->questions
                ->where('type', QuestionType::SingleChoice)
                ->sum(function ($question) use ($answers) {
                    $correct = collect($question->options_snapshot)->firstWhere('is_correct', true);

                    return ($answers->get($question->id)?->selected_option_key === ($correct['key'] ?? null)) ? (float) $question->max_score : 0;
                });

            $locked->update([
                'status' => SubmissionStatus::Submitted,
                'submitted_at' => now(),
                'reopened_until' => null,
                'objective_score' => $objectiveScore,
            ]);

            return $locked->fresh(['answers.activityQuestion']);
        });
    }
}
