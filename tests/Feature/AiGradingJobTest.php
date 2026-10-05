<?php

namespace Tests\Feature;

use App\Application\Actions\DispatchAiGradingAction;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Models\ActivityQuestion;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Grading\Enums\GradingRunStatus;
use App\Domain\Grading\Models\GradingRun;
use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Submissions\Enums\SubmissionStatus;
use App\Domain\Submissions\Models\Submission;
use App\Infrastructure\AI\ProviderTraffic;
use App\Jobs\GenerateAiSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ConfiguresGrading;
use Tests\TestCase;

class AiGradingJobTest extends TestCase
{
    use ConfiguresGrading, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureGrading();
        Queue::fake();
        Http::preventStrayRequests();
    }

    private function gradingOutput(): array
    {
        return ['score' => 8, 'criterion_scores' => [['criterion' => 'Qualidade geral da resposta', 'score' => 8, 'justification' => 'Boa resposta.']],
            'evidence' => ['Resposta do aluno.'], 'feedback' => 'Feedback ao aluno.', 'teacher_feedback' => 'Análise privada confidencial.',
            'confidence' => 0.9, 'warnings' => []];
    }

    private function gemini(): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($this->gradingOutput())]]], 'finishReason' => 'STOP']],
            'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 40]];
    }

    private function tick(GradingRun $run): void
    {
        $run->refresh();
        if ($run->next_attempt_at) {
            $this->travelTo($run->next_attempt_at->copy()->addSecond());
        }
        (new GenerateAiSuggestion($run->id))->handle();
    }

    public function test_success_is_private_deduplicated_and_preserves_human_review(): void
    {
        [$submission, $run] = $this->scenario(SubmissionStatus::Reviewed);
        Http::fake(['*' => Http::response($this->gemini())]);
        $this->tick($run);
        $this->tick($run);
        Http::assertSentCount(1);
        $this->assertSame(SubmissionStatus::Reviewed, $submission->fresh()->status);
        $this->assertSame(GradingRunStatus::Succeeded, $run->fresh()->status);
        $suggestion = $run->fresh()->suggestion;
        $this->assertSame('Análise privada confidencial.', $suggestion->teacher_feedback);
        $this->assertArrayNotHasKey('teacher_feedback', $suggestion->toArray());
        $this->assertArrayNotHasKey('request_snapshot', $run->fresh()->toArray());
        $this->assertSame('Feedback ao aluno.', $suggestion->feedback);
        $this->assertSame(100, $run->fresh()->attemptRecords->first()->input_tokens);
    }

    public function test_two_technical_failures_switch_to_openai(): void
    {
        [, $run] = $this->scenario(SubmissionStatus::Processing);
        Http::fake(['gemini.test/*' => Http::response([], 503),
            'openai.test/*' => Http::response(['output_text' => json_encode($this->gradingOutput())])]);
        $this->tick($run);
        $this->tick($run);
        $this->assertSame('fallback', $run->fresh()->wait_reason);
        $this->tick($run);
        $this->assertSame(GradingRunStatus::Succeeded, $run->fresh()->status);
        $this->assertSame(['gemini', 'gemini', 'openai'], $run->fresh()->attemptRecords->pluck('provider')->all());
        $this->assertNull($run->fresh()->estimated_cost);
        Http::assertSentCount(3);
    }

    public function test_openrouter_profile_is_dispatched_processed_and_recorded(): void
    {
        $destination = config('ai_grading.profiles.balanced.destinations.0');
        $destination['provider'] = 'openrouter';
        $destination['model'] = 'google/gemma-4-31b-it:free';
        $destination['effort'] = 'none';
        $destination['supported_efforts'] = ['none'];
        $destination['response_format'] = 'json_object';
        $destination['input_price'] = 0;
        $destination['output_price'] = 0;
        $destination['quota_group'] = 'openrouter';
        config(['services.openrouter.key' => 'test-key', 'services.openrouter.base_url' => 'https://openrouter.test/api/v1',
            'ai_grading.quotas.openrouter' => config('ai_grading.quotas.gemini'),
            'ai_grading.profiles.balanced.destinations' => [$destination]]);
        [$submission, $run] = $this->scenario(SubmissionStatus::Processing);
        $this->assertSame('openrouter', $run->execution_plan[0]['provider']);
        Http::fake(['openrouter.test/*' => Http::response([
            'choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode($this->gradingOutput())]]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 40],
        ])]);
        $this->tick($run);
        $this->assertSame(GradingRunStatus::Succeeded, $run->fresh()->status);
        $this->assertSame('openrouter', $run->fresh()->attemptRecords->first()->provider);
        $this->assertSame('google/gemma-4-31b-it:free', $run->fresh()->model);
        $this->assertSame(100, $run->fresh()->input_tokens);
        $this->assertSame('0.000000', $run->fresh()->estimated_cost);
        $this->assertSame('Análise privada confidencial.', $run->fresh()->suggestion->teacher_feedback);
        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_rate_limit_waits_over_five_minutes_without_switching_or_counting_waits(): void
    {
        [, $run] = $this->scenario(SubmissionStatus::Processing);
        Http::fake(['*' => Http::sequence()->push([], 429, ['Retry-After' => '601'])->push($this->gemini())]);
        $this->tick($run);
        $this->assertSame('rate_limit', $run->fresh()->wait_reason);
        (new GenerateAiSuggestion($run->id))->handle();
        Http::assertSentCount(1);
        $this->tick($run);
        $this->assertSame(GradingRunStatus::Succeeded, $run->fresh()->status);
        $this->assertSame(2, $run->fresh()->attempts);
        $this->assertSame(0, $run->fresh()->destination_index);
    }

    public function test_four_external_calls_are_the_hard_limit(): void
    {
        [, $run] = $this->scenario(SubmissionStatus::Processing);
        Http::fake(['*' => Http::response([], 500)]);
        for ($i = 0; $i < 6; $i++) {
            $this->tick($run);
        }
        Http::assertSentCount(4);
        $this->assertSame(GradingRunStatus::PermanentlyFailed, $run->fresh()->status);
        $this->assertNull($run->fresh()->suggestion);
    }

    public function test_invalid_response_is_not_retried_or_published(): void
    {
        [, $run] = $this->scenario(SubmissionStatus::Processing);
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"score":99}']]]]]])]);
        $this->tick($run);
        $this->tick($run);
        Http::assertSentCount(1);
        $this->assertSame('invalid_response', $run->fresh()->error_code);
        $this->assertNull($run->fresh()->suggestion);
    }

    public function test_billing_quota_does_not_fallback(): void
    {
        [, $run] = $this->scenario(SubmissionStatus::Processing);
        Http::fake(['*' => Http::response(['error' => ['code' => 'insufficient_quota']], 429)]);
        $this->tick($run);
        $this->assertSame('quota', $run->fresh()->error_code);
        $this->assertSame(0, $run->fresh()->destination_index);
        Http::assertSentCount(1);
    }

    public function test_authentication_failure_uses_alternative_without_primary_retry(): void
    {
        [, $run] = $this->scenario(SubmissionStatus::Processing);
        Http::fake(['gemini.test/*' => Http::response([], 401), 'openai.test/*' => Http::response(['output_text' => json_encode($this->gradingOutput())])]);
        $this->tick($run);
        $this->tick($run);
        $this->assertSame(GradingRunStatus::Succeeded, $run->fresh()->status);
        Http::assertSentCount(2);
    }

    public function test_expired_request_makes_no_call(): void
    {
        [, $run] = $this->scenario(SubmissionStatus::Processing);
        $run->update(['expires_at' => now()->subSecond()]);
        $this->tick($run);
        $this->assertSame('AiGradingTimeout', $run->fresh()->error_code);
        Http::assertNothingSent();
    }

    public function test_snapshot_does_not_change_when_instructions_are_edited(): void
    {
        [$submission, $run] = $this->scenario(SubmissionStatus::Processing);
        $before = $run->request_snapshot;
        $submission->activity->update(['grading_instructions' => 'Nova orientação']);
        Http::fake(['*' => Http::response($this->gemini())]);
        $this->tick($run);
        Http::assertSent(fn ($r) => ! str_contains(json_encode($r->data()), 'Nova orienta'));
        $this->assertSame($before, $run->fresh()->request_snapshot);
        $this->travel(301)->seconds();
        $other = app(DispatchAiGradingAction::class)->execute($run->answer);
        $this->assertNotSame($run->id, $other->id);
    }

    public function test_every_profile_and_detail_is_snapshotted_and_reused(): void
    {
        [$submission, $run] = $this->scenario(SubmissionStatus::Processing);
        $action = app(DispatchAiGradingAction::class);
        $run->update(['status' => GradingRunStatus::Succeeded, 'finished_at' => now()]);
        $this->travel(301)->seconds();
        foreach (['economy', 'balanced', 'advanced'] as $profile) {
            foreach (['short', 'medium', 'detailed'] as $detail) {
                $options = ['intelligence_profile' => $profile, 'feedback_detail' => $detail];
                $first = $action->execute($run->answer, $options);
                $second = $action->execute($run->answer, $options);
                $this->assertSame($first->id, $second->id);
                $this->assertSame($profile, $first->request_snapshot['intelligenceProfile']);
                $this->assertSame($detail, $first->request_snapshot['feedbackDetail']);
                $first->update(['status' => GradingRunStatus::Succeeded, 'finished_at' => now()]);
                $this->travel(301)->seconds();
            }
        }
        $this->assertDatabaseCount('grading_runs', 9);
    }

    public function test_quota_permit_blocks_concurrent_call_and_wait_does_not_consume_attempt(): void
    {
        [, $run] = $this->scenario(SubmissionStatus::Processing);
        $traffic = app(ProviderTraffic::class);
        $destination = $run->execution_plan[0];
        $permit = $traffic->acquire($destination);
        $this->assertArrayHasKey('token', $permit);
        $this->tick($run);
        $this->assertSame(0, $run->fresh()->attempts);
        $this->assertSame('rate_limit', $run->fresh()->wait_reason);
        Http::assertNothingSent();
        $traffic->release($destination, $permit['token']);
    }

    public function test_run_lock_prevents_two_workers_sending_the_same_request(): void
    {
        [, $run] = $this->scenario(SubmissionStatus::Processing);
        $lock = app(ProviderTraffic::class)->store()->lock('ai:run:'.$run->id, 100);
        $lock->get();
        $this->tick($run);
        Http::assertNothingSent();
        $lock->release();
        Http::fake(['*' => Http::response($this->gemini())]);
        $this->tick($run);
        $this->assertSame(1, $run->fresh()->attempts);
    }

    public function test_interrupted_attempt_remains_counted_with_unknown_consumption(): void
    {
        [, $run] = $this->scenario(SubmissionStatus::Processing);
        $run->update(['attempts' => 1, 'status' => GradingRunStatus::Processing]);
        $run->attemptRecords()->create(['number' => 1, 'provider' => 'gemini', 'model' => 'test', 'started_at' => now()->subMinutes(2)]);
        Http::fake(['*' => Http::response($this->gemini())]);
        $this->tick($run);
        $this->assertSame(2, $run->fresh()->attempts);
        $this->assertSame('interrupted', $run->fresh()->attemptRecords->first()->status);
        $this->assertNull($run->fresh()->attemptRecords->first()->input_tokens);
    }

    public function test_timeout_is_spaced_and_can_recover_on_primary(): void
    {
        [, $run] = $this->scenario(SubmissionStatus::Processing);
        $calls = 0;
        Http::fake(function () use (&$calls) {
            if (++$calls === 1) {
                throw new ConnectionException('cURL error 28');
            }

            return Http::response($this->gemini());
        });
        $this->tick($run);
        $this->assertSame('technical_retry', $run->fresh()->wait_reason);
        $this->assertTrue($run->fresh()->next_attempt_at->isFuture());
        $this->tick($run);
        $this->assertSame(GradingRunStatus::Succeeded, $run->fresh()->status);
        $this->assertSame(0, $run->fresh()->destination_index);
    }

    public function test_no_alternative_ends_after_two_technical_calls(): void
    {
        config(['ai_grading.profiles.balanced.destinations.1.enabled' => false]);
        [, $run] = $this->scenario(SubmissionStatus::Processing);
        Http::fake(['*' => Http::response([], 503)]);
        $this->tick($run);
        $this->tick($run);
        $this->assertSame(GradingRunStatus::PermanentlyFailed, $run->fresh()->status);
        Http::assertSentCount(2);
    }

    public function test_certificate_failure_is_terminal(): void
    {
        [, $run] = $this->scenario(SubmissionStatus::Processing);
        Http::fake(fn () => throw new ConnectionException('cURL error 60'));
        $this->tick($run);
        $this->assertSame('certificate', $run->fresh()->error_code);
        $this->assertSame(1, $run->fresh()->attempts);
    }

    public function test_refusal_keeps_known_usage_but_no_suggestion(): void
    {
        [, $run] = $this->scenario(SubmissionStatus::Processing);
        Http::fake(['*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY'], 'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 0]])]);
        $this->tick($run);
        $this->assertSame(GradingRunStatus::PermanentlyFailed, $run->fresh()->status);
        $this->assertSame(100, $run->fresh()->attemptRecords->first()->input_tokens);
        $this->assertNull($run->fresh()->suggestion);
    }

    private function scenario(SubmissionStatus $status): array
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $activity = Activity::query()->create([
            'teacher_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'title' => 'Redação',
            'status' => ActivityStatus::Grading,
            'deadline_at' => now()->subHour(),
            'total_score' => 10,
        ]);
        $question = ActivityQuestion::query()->create([
            'activity_id' => $activity->id,
            'type' => QuestionType::Essay,
            'body' => 'Explique o tema.',
            'max_score' => 10,
            'rubric_snapshot' => [],
            'position' => 1,
        ]);
        $submission = Submission::query()->create([
            'activity_id' => $activity->id,
            'student_id' => $student->id,
            'status' => $status,
            'submitted_at' => now(),
        ]);
        $answer = $submission->answers()->create([
            'activity_question_id' => $question->id,
            'response_text' => 'Resposta do aluno.',
            'version' => 1,
        ]);
        $run = app(DispatchAiGradingAction::class)->execute($answer);

        return [$submission, $run];
    }
}
