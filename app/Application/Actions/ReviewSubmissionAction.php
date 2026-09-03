<?php

namespace App\Application\Actions;

use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Grading\Models\GradingDecision;
use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Submissions\Enums\SubmissionStatus;
use App\Domain\Submissions\Models\Submission;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReviewSubmissionAction
{
    public function execute(Submission $submission, User $reviewer, array $grades): Submission
    {
        return DB::transaction(function () use ($submission, $reviewer, $grades) {
            $locked = Submission::query()->with('answers.activityQuestion')->lockForUpdate()->findOrFail($submission->id);
            if (! in_array($locked->status, [SubmissionStatus::Submitted, SubmissionStatus::Processing, SubmissionStatus::Reviewed], true)) {
                throw new DomainException('Esta entrega nao pode ser corrigida agora.');
            }

            foreach ($locked->answers as $answer) {
                if ($answer->activityQuestion->type === QuestionType::SingleChoice) {
                    continue;
                }
                $grade = $grades[$answer->id] ?? null;
                if (! is_array($grade)) {
                    throw new DomainException('Informe a nota de todas as respostas.');
                }
                $score = (float) ($grade['score'] ?? -1);
                if ($score < 0 || $score > (float) $answer->activityQuestion->max_score) {
                    throw new DomainException('Uma nota esta fora do limite da questao.');
                }
                $suggestion = $answer->gradingRuns()->where('status', 'succeeded')->latest()->first()?->suggestion;
                GradingDecision::query()->updateOrCreate(['answer_id' => $answer->id], [
                    'grading_suggestion_id' => $suggestion?->id,
                    'reviewer_id' => $reviewer->id,
                    'score' => $score,
                    'feedback' => $grade['feedback'] ?? null,
                    'confirmed_at' => now(),
                ]);
            }

            $finalScore = (float) $locked->objective_score + (float) GradingDecision::query()
                ->whereIn('answer_id', $locked->answers->pluck('id'))->sum('score');
            $locked->update(['status' => SubmissionStatus::Reviewed, 'reviewed_at' => now(), 'final_score' => $finalScore]);
            $hasPendingReview = Submission::query()->where('activity_id', $locked->activity_id)->whereIn('status', [SubmissionStatus::Submitted, SubmissionStatus::Processing])->exists();
            if (! $hasPendingReview && ($locked->activity->deadline_at?->isPast() ?? false)) {
                $locked->activity->update(['status' => ActivityStatus::ReviewReady]);
            }

            return $locked->fresh();
        });
    }
}
