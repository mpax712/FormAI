<?php

namespace App\Application\Actions;

use App\Domain\Grading\Enums\GradingRunStatus;
use App\Domain\Grading\Models\GradingRun;
use App\Domain\Submissions\Enums\SubmissionStatus;
use App\Domain\Submissions\Models\Submission;
use Illuminate\Support\Facades\DB;

class CancelExpiredAiGradingRunsAction
{
    public function execute(Submission $submission): int
    {
        $timeout = (int) config('formai.grading_timeout_seconds', 180);
        $activeStatuses = [GradingRunStatus::Pending, GradingRunStatus::Processing, GradingRunStatus::RetryableFailed];

        return DB::transaction(function () use ($submission, $timeout, $activeStatuses): int {
            $expiredRuns = GradingRun::query()
                ->whereHas('answer', fn ($query) => $query->where('submission_id', $submission->id))
                ->whereIn('status', $activeStatuses)
                ->where('created_at', '<=', now()->subSeconds($timeout))
                ->lockForUpdate()
                ->get();

            foreach ($expiredRuns as $run) {
                $run->update([
                    'status' => GradingRunStatus::PermanentlyFailed,
                    'finished_at' => now(),
                    'error_code' => 'AiGradingTimeout',
                    'error_message' => "Correção cancelada porque ultrapassou o limite de {$timeout} segundos.",
                ]);
            }

            if ($expiredRuns->isNotEmpty()) {
                $stillActive = GradingRun::query()
                    ->whereHas('answer', fn ($query) => $query->where('submission_id', $submission->id))
                    ->whereIn('status', $activeStatuses)
                    ->exists();

                if (! $stillActive) {
                    Submission::query()->whereKey($submission->id)->where('status', SubmissionStatus::Processing)
                        ->update(['status' => SubmissionStatus::Submitted]);
                }
            }

            return $expiredRuns->count();
        });
    }
}
