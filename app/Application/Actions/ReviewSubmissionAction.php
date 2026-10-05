<?php

namespace App\Application\Actions;

use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Administration\Models\AuditLog;
use App\Domain\Grading\Models\GradingDecision;
use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Submissions\Enums\SubmissionStatus;
use App\Domain\Submissions\Models\Submission;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReviewSubmissionAction
{
    public function executeAndPublish(Submission $submission, User $reviewer, array $grades): Submission
    {
        return DB::transaction(function () use ($submission, $reviewer, $grades) {
            $reviewed = $this->execute($submission, $reviewer, $grades);
            return $reviewed->status === SubmissionStatus::Released
                ? $reviewed
                : $this->releaseReviewed($reviewed);
        });
    }

    public function releaseReviewed(Submission $submission): Submission
    {
        return DB::transaction(function () use ($submission) {
            $locked = Submission::query()->lockForUpdate()->findOrFail($submission->id);
            if ($locked->status !== SubmissionStatus::Reviewed) {
                throw new DomainException('Revise todas as respostas antes de publicar.');
            }
            $locked->update(['status' => SubmissionStatus::Released, 'released_at' => now()]);
            $activity = $locked->activity()->lockForUpdate()->firstOrFail();
            $hasUnreleased = $activity->submissions()
                ->whereIn('status', [SubmissionStatus::Submitted, SubmissionStatus::Processing, SubmissionStatus::Reviewed])
                ->exists();
            $hasReopenedDraft = $activity->submissions()->where('status', SubmissionStatus::Draft)
                ->where('reopened_until', '>', now())->exists();
            if ($activity->deadline_at?->isPast() && ! $hasUnreleased && ! $hasReopenedDraft) {
                $activity->update(['status' => ActivityStatus::Released, 'released_at' => now()]);
            }

            return $locked->fresh();
        });
    }

    public function execute(Submission $submission, User $reviewer, array $grades): Submission
    {
        return DB::transaction(function () use ($submission, $reviewer, $grades) {
            $locked = Submission::query()->with('answers.activityQuestion')->lockForUpdate()->findOrFail($submission->id);
            if (! in_array($locked->status, [SubmissionStatus::Submitted, SubmissionStatus::Processing, SubmissionStatus::Reviewed, SubmissionStatus::Released], true)) {
                throw new DomainException('Esta entrega nao pode ser corrigida agora.');
            }
            $republishing = $locked->status === SubmissionStatus::Released;
            $previousDecisions = $republishing
                ? GradingDecision::query()->whereIn('answer_id', $locked->answers->pluck('id'))
                    ->get()->mapWithKeys(fn (GradingDecision $decision) => [$decision->answer_id => [
                        'score' => $decision->score,
                        'feedback' => $decision->feedback,
                    ]])->all()
                : [];
            $previousScore = $locked->final_score;

            foreach ($locked->answers as $answer) {
                $objective = in_array($answer->activityQuestion->type, [QuestionType::SingleChoice, QuestionType::MultipleChoice], true);
                $grade = $grades[$answer->id] ?? null;
                if ($objective && $grade === null) continue;
                if (! is_array($grade)) {
                    throw new DomainException('Informe a nota de todas as respostas.');
                }
                $score = (float) ($grade['score'] ?? -1);
                if ($score < 0 || $score > (float) $answer->activityQuestion->max_score) {
                    throw new DomainException('Uma nota esta fora do limite da questao.');
                }
                $suggestion = $objective ? null : $answer->gradingRuns()->where('status', 'succeeded')->latest()->first()?->suggestion;
                GradingDecision::query()->updateOrCreate(['answer_id' => $answer->id], [
                    'grading_suggestion_id' => $suggestion?->id,
                    'reviewer_id' => $reviewer->id,
                    'score' => $score,
                    'feedback' => $grade['feedback'] ?? null,
                    'confirmed_at' => now(),
                ]);
            }

            $decisions = GradingDecision::query()->whereIn('answer_id', $locked->answers->pluck('id'))->get()->keyBy('answer_id');
            $finalScore = (float) $locked->objective_score;
            foreach ($locked->answers as $answer) {
                $decision = $decisions->get($answer->id);
                if (! $decision) continue;
                if (in_array($answer->activityQuestion->type, [QuestionType::SingleChoice, QuestionType::MultipleChoice], true)) {
                    $finalScore += (float) $decision->score - $this->automaticScore($answer);
                } else {
                    $finalScore += (float) $decision->score;
                }
            }
            $locked->update([
                'status' => $republishing ? SubmissionStatus::Released : SubmissionStatus::Reviewed,
                'reviewed_at' => now(),
                'released_at' => $republishing ? now() : $locked->released_at,
                'final_score' => $finalScore,
            ]);
            if ($republishing) {
                $currentDecisions = GradingDecision::query()->whereIn('answer_id', $locked->answers->pluck('id'))
                    ->get()->mapWithKeys(fn (GradingDecision $decision) => [$decision->answer_id => [
                        'score' => $decision->score,
                        'feedback' => $decision->feedback,
                    ]])->all();
                AuditLog::query()->create([
                    'actor_id' => $reviewer->id,
                    'event' => 'submission.grade_republished',
                    'auditable_type' => Submission::class,
                    'auditable_id' => (string) $locked->id,
                    'route' => 'teacher.grading.review',
                    'metadata' => [
                        'submission_id' => $locked->id,
                        'activity_id' => $locked->activity_id,
                        'previous_score' => $previousScore,
                        'new_score' => $finalScore,
                        'previous_decisions' => $previousDecisions,
                        'new_decisions' => $currentDecisions,
                    ],
                ]);
            } else {
                $hasPendingReview = Submission::query()->where('activity_id', $locked->activity_id)->whereIn('status', [SubmissionStatus::Submitted, SubmissionStatus::Processing])->exists();
                if (! $hasPendingReview && ($locked->activity->deadline_at?->isPast() ?? false)) {
                    $locked->activity->update(['status' => ActivityStatus::ReviewReady]);
                }
            }

            return $locked->fresh();
        });
    }

    private function automaticScore(\App\Domain\Submissions\Models\Answer $answer): float
    {
        $correct = collect($answer->activityQuestion->options_snapshot ?? [])
            ->filter(fn ($option) => (bool) ($option['is_correct'] ?? false))->pluck('key')->sort()->values()->all();
        $selected = explode(',', (string) $answer->selected_option_key);
        sort($selected);

        return $selected === $correct ? (float) $answer->activityQuestion->max_score : 0.0;
    }
}
