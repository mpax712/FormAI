<?php

namespace App\Application\Actions;

use App\Domain\Grading\Enums\GradingRunStatus;
use App\Domain\Grading\Models\GradingRun;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Submissions\Models\Answer;
use App\Infrastructure\AI\GradingProfiles;
use App\Infrastructure\AI\GradingSnapshot;
use App\Jobs\GenerateAiSuggestion;
use DomainException;
use Illuminate\Support\Facades\DB;

class DispatchAiGradingAction
{
    public function execute(Answer $answer, array $options = []): GradingRun
    {
        return DB::transaction(function () use ($answer, $options) {
            $answer = Answer::whereKey($answer->id)->lockForUpdate()->firstOrFail();
            $answer->load('activityQuestion.activity', 'submission');
            if ($answer->activityQuestion->type !== QuestionType::Essay) {
                throw new DomainException('A IA é utilizada somente em questões dissertativas.');
            }
            $profiles = app(GradingProfiles::class);
            $settings = $profiles->options($options, $answer->activityQuestion->activity);
            $snapshots = app(GradingSnapshot::class);
            $snapshot = $snapshots->capture($answer, $settings);
            $plan = $profiles->destinations($settings['intelligence_profile'], $settings['feedback_detail'],
                $snapshots->inputBytes($snapshot), count($snapshot['rubric']) ?: 1);
            // Quota/price changes alone must not cause another paid analysis of identical content.
            $requestPlan = array_map(fn ($destination) => \Illuminate\Support\Arr::only($destination, ['provider', 'model', 'effort', 'response_format', 'max_output_tokens']), $plan);
            $key = hash('sha256', json_encode([$answer->id, $answer->version, $answer->submission->version, $snapshot, $requestPlan], JSON_THROW_ON_ERROR));
            $active = $answer->gradingRuns()->whereIn('status', [GradingRunStatus::Pending, GradingRunStatus::Processing, GradingRunStatus::RetryableFailed])->latest('id')->first();
            if ($active) {
                if ($active->idempotency_key === $key) {
                    return $active;
                }
                throw new DomainException('Já existe uma correção com IA em andamento para esta questão. Aguarde a conclusão.');
            }
            $existing = GradingRun::where('idempotency_key', $key)->first();
            if ($existing?->status === GradingRunStatus::Succeeded) {
                return $existing;
            }
            $latest = $answer->gradingRuns()->latest('id')->first();
            if ($latest) {
                $remaining = $latest->retryAvailableAt()->timestamp - now()->timestamp;
                if ($remaining > 0) {
                    throw new DomainException('Aguarde '.(int) ceil($remaining / 60).' minuto(s) antes de solicitar outra correção com IA para esta questão.');
                }
            }
            if ($existing?->suggestion()->exists()) {
                throw new DomainException('Esta solicitação já possui uma sugestão e não pode ser substituída.');
            }

            // Keep completed suggestions and provider quota reservations; replace only terminal failures.
            $answer->gradingRuns()->where('status', GradingRunStatus::PermanentlyFailed)->whereDoesntHave('suggestion')->delete();

            $snapshot['idempotencyKey'] = $key;
            $snapshot['_answer_version'] = (int) $answer->version;
            $snapshot['_submission_version'] = (int) $answer->submission->version;
            $run = GradingRun::create([
                'answer_id' => $answer->id, 'idempotency_key' => $key, 'status' => GradingRunStatus::Pending,
                'provider' => $plan[0]['provider'], 'model' => $plan[0]['model'], 'prompt_version' => $snapshot['promptVersion'],
                'request_snapshot' => $snapshot, 'execution_plan' => $plan,
                'expires_at' => now()->addSeconds(config('ai_grading.queue_timeout_seconds')),
            ]);
            GenerateAiSuggestion::dispatch($run->id)->onQueue('ai')->afterCommit();

            return $run;
        });
    }
}
