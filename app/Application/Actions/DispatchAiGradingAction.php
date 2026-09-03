<?php

namespace App\Application\Actions;

use App\Domain\Grading\Enums\GradingRunStatus;
use App\Domain\Grading\Models\GradingRun;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Submissions\Models\Answer;
use App\Jobs\GenerateAiSuggestion;
use App\Infrastructure\AI\AiProviderConfiguration;
use DomainException;

class DispatchAiGradingAction
{
    public function __construct(private readonly AiProviderConfiguration $configuration = new AiProviderConfiguration) {}

    public function execute(Answer $answer): GradingRun
    {
        if (! $this->configuration->isConfigured()) {
            throw new DomainException('Configure a '.$this->configuration->keyEnvironmentName().' antes de solicitar uma correção com IA.');
        }

        $answer->loadMissing('activityQuestion');
        if ($answer->activityQuestion->type !== QuestionType::Essay) {
            throw new DomainException('A IA e utilizada somente em questoes dissertativas.');
        }

        $version = (int) config('formai.prompt_version');
        $idempotencyKey = hash('sha256', implode(':', [$answer->id, $answer->version, $version, $this->configuration->provider(), $this->configuration->model()]));
        $run = GradingRun::query()->firstOrCreate(['idempotency_key' => $idempotencyKey], [
            'answer_id' => $answer->id,
            'status' => GradingRunStatus::Pending,
            'provider' => $this->configuration->provider(),
            'model' => $this->configuration->model(),
            'prompt_version' => $version,
        ]);

        $shouldDispatch = $run->wasRecentlyCreated;
        if ($run->status === GradingRunStatus::PermanentlyFailed) {
            $run->update([
                'status' => GradingRunStatus::Pending,
                'attempts' => 0,
                'started_at' => null,
                'finished_at' => null,
                'error_code' => null,
                'error_message' => null,
            ]);
            $shouldDispatch = true;
        }

        if ($shouldDispatch) {
            GenerateAiSuggestion::dispatch($run->id)->onQueue('ai')->afterCommit();
        }

        return $run;
    }
}
