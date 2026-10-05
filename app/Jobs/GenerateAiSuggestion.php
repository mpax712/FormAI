<?php

namespace App\Jobs;

use App\Application\DTOs\GradingRequest;
use App\Domain\Grading\Enums\GradingRunStatus;
use App\Domain\Grading\Models\GradingRun;
use App\Domain\Submissions\Enums\SubmissionStatus;
use App\Domain\Submissions\Models\Submission;
use App\Infrastructure\AI\Exceptions\InvalidGradingResponse;
use App\Infrastructure\AI\Exceptions\ProviderFailure;
use App\Infrastructure\AI\Exceptions\RetryableAiException;
use App\Infrastructure\AI\GeminiGradingProvider;
use App\Infrastructure\AI\OpenAiGradingProvider;
use App\Infrastructure\AI\OpenRouterGradingProvider;
use App\Infrastructure\AI\ProviderTraffic;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateAiSuggestion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    public int $timeout = 90;

    // Infrastructure errors (database/cache); provider retries are handled explicitly below.
    public array $backoff = [20, 60, 120];

    public function __construct(public readonly int $gradingRunId) {}

    public function retryUntil(): \DateTimeInterface
    {
        return GradingRun::find($this->gradingRunId)?->expires_at ?? now()->addSeconds(config('ai_grading.queue_timeout_seconds'));
    }

    public function handle(): void
    {
        $traffic = app(ProviderTraffic::class);
        $lock = $traffic->store()->lock('ai:run:'.$this->gradingRunId, config('ai_grading.lease_seconds'));
        if (! $lock->get()) {
            $this->release(15);

            return;
        }
        try {
            $this->process($traffic);
        } finally {
            $lock->release();
        }
    }

    private function process(ProviderTraffic $traffic): void
    {
        $run = GradingRun::with('answer.submission')->find($this->gradingRunId);
        if (! $run || in_array($run->status, [GradingRunStatus::Succeeded, GradingRunStatus::PermanentlyFailed], true)) {
            return;
        }
        if (! $run->request_snapshot || ! $run->execution_plan) {
            $this->finishFailure($run, 'legacy_request', 'Pedido antigo sem configuração validada. Solicite uma nova análise.');

            return;
        }
        if ($run->expires_at->isPast()) {
            $this->finishFailure($run, 'AiGradingTimeout', 'Prazo global da correção encerrado.');

            return;
        }
        if ($run->next_attempt_at?->isFuture()) {
            $this->release(max(1, $run->next_attempt_at->timestamp - now()->timestamp));

            return;
        }
        $snapshot = $run->request_snapshot;
        if ($run->answer->version != $snapshot['_answer_version'] || $run->answer->submission->version != $snapshot['_submission_version']
            || $run->answer->submission->status === SubmissionStatus::Draft) {
            $this->finishFailure($run, 'superseded', 'A entrega foi alterada ou reaberta; solicite uma nova análise.');

            return;
        }
        // A crashed worker may have sent its request. Keep that attempt charged, with unknown usage.
        $interrupted = $run->attemptRecords()->whereNull('finished_at')->update(['status' => 'interrupted', 'reason' => 'worker_interrupted', 'finished_at' => now()]);
        if ($interrupted) {
            $run->increment('technical_failures');
            if ($run->technical_failures >= 2 && $this->alternate($run, 'worker_interrupted')) {
                return;
            }
        }
        if ($run->attempts >= config('ai_grading.max_calls')) {
            $this->finishFailure($run, 'attempt_limit', 'Limite de quatro chamadas atingido. A correção manual continua disponível.');

            return;
        }
        $destination = $run->execution_plan[$run->destination_index];
        $permit = $traffic->acquire($destination);
        if (! isset($permit['token'])) {
            if ($permit['reason'] === 'provider_paused' && $this->alternate($run, 'provider_paused')) {
                return;
            }
            $this->defer($run, $permit['delay'], $permit['reason']);

            return;
        }
        try {
            $number = $run->attempts + 1;
            $attempt = DB::transaction(function () use ($run, $destination, $number) {
                $attempt = $run->attemptRecords()->create([
                    'number' => $number, 'provider' => $destination['provider'], 'model' => $destination['model'],
                    'status' => 'processing', 'reason' => $run->wait_reason, 'started_at' => now(),
                ]);
                $run->update(['attempts' => $number, 'provider' => $destination['provider'], 'model' => $destination['model'],
                    'status' => GradingRunStatus::Processing, 'started_at' => $run->started_at ?? now(),
                    'next_attempt_at' => null, 'wait_reason' => null, 'error_code' => null, 'error_message' => null]);

                return $attempt;
            });
            $started = microtime(true);
            unset($snapshot['_answer_version'], $snapshot['_submission_version']);
            $snapshot['destination'] = $destination;
            $provider = app(match ($destination['provider']) {
                'gemini' => GeminiGradingProvider::class,
                'openai' => OpenAiGradingProvider::class,
                'openrouter' => OpenRouterGradingProvider::class,
                default => throw new \RuntimeException('Provedor de IA não suportado.'),
            });
            try {
                $result = $provider->grade(new GradingRequest(...$snapshot));
            } catch (Throwable $exception) {
                $kind = $exception instanceof ProviderFailure ? $exception->kind : ($exception instanceof RetryableAiException ? 'technical' : 'invalid_response');
                if ($exception instanceof InvalidGradingResponse) {
                    $attempt->update(['input_tokens' => $exception->inputTokens, 'output_tokens' => $exception->outputTokens,
                        'estimated_cost' => $exception->inputTokens !== null && $exception->outputTokens !== null
                            ? ($exception->inputTokens * $destination['input_price'] + $exception->outputTokens * $destination['output_price']) / 1_000_000 : null]);
                }
                $attempt->update(['status' => 'failed', 'reason' => $kind, 'finished_at' => now(), 'duration_ms' => (int) ((microtime(true) - $started) * 1000)]);
                $delay = $exception instanceof ProviderFailure && $exception->retryAfter > 0
                    ? $exception->retryAfter : min(300, 20 * (2 ** ($number - 1))) + random_int(0, 5);
                $traffic->failure($destination, $kind, $kind === 'quota' ? 86400 : $delay);
                if ($kind === 'technical') {
                    $run->increment('technical_failures');
                }
                if ($number < config('ai_grading.max_calls')) {
                    if ($kind === 'rate_limit') {
                        $this->defer($run, $delay, 'rate_limit');

                        return;
                    }
                    if ($kind === 'unavailable' || ($kind === 'technical' && $run->technical_failures >= 2)) {
                        if ($this->alternate($run, $kind)) {
                            return;
                        }
                    } elseif ($kind === 'technical') {
                        $this->defer($run, $delay, 'technical_retry');

                        return;
                    }
                }
                $message = match ($kind) {
                    'quota' => 'Quota esgotada ou cobrança pendente no provedor. Verifique a conta.',
                    'certificate' => 'Falha de certificado TLS. Verifique a configuração do servidor.',
                    'unavailable' => 'Destino indisponível ou credencial inválida; não há alternativa válida.',
                    'rate_limit' => 'Limite de chamadas da execução atingido enquanto a API limitava requisições.',
                    'technical' => 'Os provedores não conseguiram concluir a correção.',
                    default => 'A IA recusou o pedido ou retornou uma resposta inválida. Nenhuma nota foi publicada.',
                };
                $this->finishFailure($run, $kind, $message);

                return;
            }
            $cost = $result->inputTokens !== null && $result->outputTokens !== null
                ? ($result->inputTokens * $destination['input_price'] + $result->outputTokens * $destination['output_price']) / 1_000_000 : null;
            $attempt->update(['status' => 'succeeded', 'finished_at' => now(), 'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                'input_tokens' => $result->inputTokens, 'output_tokens' => $result->outputTokens, 'estimated_cost' => $cost]);
            $traffic->success($destination);
            DB::transaction(function () use ($run, $result, $cost) {
                $locked = GradingRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
                if (in_array($locked->status, [GradingRunStatus::Succeeded, GradingRunStatus::PermanentlyFailed], true)) {
                    return;
                }
                $answer = $locked->answer()->first();
                if ($locked->expires_at->isPast() || $answer->version != $locked->request_snapshot['_answer_version']
                    || $answer->submission->version != $locked->request_snapshot['_submission_version']) {
                    $this->finishFailure($locked, 'superseded', 'O prazo encerrou ou a entrega mudou durante a análise.');

                    return;
                }
                $locked->suggestion()->create([
                    'score' => $result->score, 'criterion_scores' => $result->criterionScores,
                    'evidence' => $result->evidence, 'feedback' => $result->feedback, 'teacher_feedback' => $result->teacherFeedback,
                    'confidence' => $result->confidence, 'warnings' => $result->warnings, 'created_at' => now(),
                ]);
                $locked->update(['status' => GradingRunStatus::Succeeded, 'finished_at' => now(),
                    'input_tokens' => $result->inputTokens, 'output_tokens' => $result->outputTokens, 'estimated_cost' => $cost]);
            });
            $this->markReviewReadyWhenComplete($run);
        } finally {
            $traffic->release($destination, $permit['token']);
        }
    }

    private function alternate(GradingRun $run, string $reason): bool
    {
        if ($run->attempts >= config('ai_grading.max_calls') || ! isset($run->execution_plan[$run->destination_index + 1])) {
            return false;
        }
        $run->update(['destination_index' => $run->destination_index + 1, 'technical_failures' => 0, 'error_code' => $reason]);
        $this->defer($run, 20 + random_int(0, 5), 'fallback');

        return true;
    }

    private function defer(GradingRun $run, int $seconds, string $reason): void
    {
        $seconds = max(1, min($seconds, max(1, $run->expires_at->timestamp - now()->timestamp)));
        $run->update(['status' => GradingRunStatus::RetryableFailed, 'wait_reason' => $reason, 'next_attempt_at' => now()->addSeconds($seconds)]);
        $this->release($seconds);
    }

    private function finishFailure(GradingRun $run, string $code, string $message): void
    {
        $run->update(['status' => GradingRunStatus::PermanentlyFailed, 'finished_at' => now(),
            'error_code' => $code, 'error_message' => $message, 'next_attempt_at' => null]);
        $this->markReviewReadyWhenComplete($run);
    }

    public function failed(?Throwable $exception): void
    {
        $run = GradingRun::with('answer.submission')->find($this->gradingRunId);
        if ($run && ! in_array($run->status, [GradingRunStatus::Succeeded, GradingRunStatus::PermanentlyFailed], true)) {
            $this->finishFailure($run, 'worker_failure', 'O worker não concluiu a execução dentro do prazo. Verifique os logs do servidor.');
        }
    }

    private function markReviewReadyWhenComplete(GradingRun $run): void
    {
        $submissionId = $run->answer->submission_id;
        $pending = GradingRun::whereHas('answer', fn ($q) => $q->where('submission_id', $submissionId))
            ->whereIn('status', [GradingRunStatus::Pending, GradingRunStatus::Processing, GradingRunStatus::RetryableFailed])->exists();
        if (! $pending) {
            Submission::whereKey($submissionId)->where('status', SubmissionStatus::Processing)->update(['status' => SubmissionStatus::Submitted]);
        }
    }
}
