<?php

use App\Infrastructure\Observability\Models\SystemHeartbeat;
use App\Application\Actions\CancelExpiredAiGradingRunsAction;
use App\Domain\Grading\Enums\GradingRunStatus;
use App\Domain\Grading\Models\GradingRun;
use App\Domain\Submissions\Models\Submission;
use Illuminate\Support\Facades\Schedule;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

Schedule::call(function () {
    SystemHeartbeat::query()->updateOrCreate(['name' => 'scheduler'], ['last_seen_at' => now(), 'metadata' => ['host' => gethostname() ?: 'unknown']]);
})->everyMinute()->name('scheduler-heartbeat')->withoutOverlapping();

Schedule::command('queue:work database --queue=ai,default --stop-when-empty --max-time=50 --tries=3 --timeout=60')
    ->everyMinute()->name('short-lived-queue-worker')->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('model:prune')->daily();

Schedule::call(function () {
    $timeout = (int) config('formai.grading_timeout_seconds', 180);
    $submissionIds = GradingRun::query()
        ->whereIn('status', [GradingRunStatus::Pending, GradingRunStatus::Processing, GradingRunStatus::RetryableFailed])
        ->where('created_at', '<=', now()->subSeconds($timeout))
        ->with('answer:id,submission_id')
        ->get()
        ->pluck('answer.submission_id')
        ->filter()
        ->unique();

    Submission::query()->whereKey($submissionIds->all())->each(
        fn (Submission $submission) => app(CancelExpiredAiGradingRunsAction::class)->execute($submission)
    );
})->everyMinute()->name('cancel-expired-ai-grading')->withoutOverlapping();

Schedule::call(function () {
    Activity::query()->where('status', ActivityStatus::Published->value)->where('deadline_at', '<=', now())->each(function (Activity $activity) {
        $activity->update(['status' => $activity->submissions()->whereNotNull('submitted_at')->exists() ? ActivityStatus::Grading : ActivityStatus::Closed]);
    });
})->everyMinute()->name('close-expired-activities')->withoutOverlapping();

Schedule::call(function () {
    User::query()->whereNull('anonymized_at')->whereNotNull('deleted_requested_at')->where('deleted_requested_at', '<=', now()->subDays(30))->each(function (User $user) {
        $user->forceFill([
            'name' => 'Usuario removido '.$user->public_id,
            'email' => 'deleted+'.$user->public_id.'@invalid.local',
            'password' => Hash::make(str()->random(64)),
            'email_verified_at' => null,
            'mfa_code_hash' => null,
            'mfa_expires_at' => null,
            'anonymized_at' => now(),
        ])->save();
    });
})->daily()->name('anonymize-deleted-accounts')->withoutOverlapping();
