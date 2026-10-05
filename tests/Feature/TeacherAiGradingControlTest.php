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
use App\Jobs\GenerateAiSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TeacherAiGradingControlTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\ConfiguresGrading;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureGrading();
    }

    public function test_bulk_grading_is_owner_scoped_and_idempotent(): void
    {
        Queue::fake();
        config(['services.openai.key' => 'test-key']);
        [$teacher, $submission] = $this->scenario();
        $url = route('teacher.grading.ai-activity', $submission->activity);
        foreach (['draft', 'reviewed', 'released'] as $status) {
            $excluded = $submission->activity->submissions()->create(['student_id' => User::factory()->student()->create()->id, 'status' => $status]);
            $excluded->answers()->create(['activity_question_id' => $submission->answers->first()->activity_question_id, 'response_text' => 'Não deve solicitar análise.']);
        }
        $this->actingAs(User::factory()->teacher()->create())->post($url)->assertForbidden();
        $this->assertDatabaseCount('grading_runs', 0);
        $this->actingAs($teacher)->post($url)->assertSessionHasNoErrors();
        $this->post($url)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('grading_runs', 2);
        Queue::assertPushed(GenerateAiSuggestion::class, 2);
    }

    public function test_teacher_can_request_ai_for_all_essay_answers(): void
    {
        Queue::fake();
        config(['services.openai.key' => 'test-key']);
        [$teacher, $submission] = $this->scenario();

        $response = $this->actingAs($teacher)->post(route('teacher.grading.ai-all', $submission));

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::Processing, $submission->fresh()->status);
        $this->assertDatabaseCount('grading_runs', 2);
        Queue::assertPushed(GenerateAiSuggestion::class, 2);
    }

    public function test_activity_page_offers_manual_and_ai_correction_before_opening_submission(): void
    {
        config(['services.openai.key' => 'test-key']);
        [$teacher, $submission] = $this->scenario();

        $this->actingAs($teacher)->get(route('teacher.activities.show', $submission->activity))
            ->assertOk()
            ->assertSee('Corrigir manualmente')
            ->assertSee('Corrigir com IA')
            ->assertSee(route('teacher.grading.ai-all', $submission), false);
    }

    public function test_ai_status_explains_a_permanent_failure(): void
    {
        [$teacher, $submission, $answers] = $this->scenario();
        GradingRun::query()->create([
            'answer_id' => $answers[0]->id,
            'idempotency_key' => str_repeat('f', 64),
            'status' => GradingRunStatus::PermanentlyFailed,
            'provider' => 'openai',
            'model' => 'test-model',
            'prompt_version' => 1,
            'error_code' => 'TimeoutException',
            'error_message' => 'O serviço demorou mais que o limite permitido.',
        ]);

        $this->actingAs($teacher)->getJson(route('teacher.grading.ai-status', $submission))
            ->assertOk()
            ->assertJsonPath('state', 'failed')
            ->assertJsonPath('errors.0.message', 'O serviço demorou mais que o limite permitido.');
    }

    public function test_ai_status_explains_that_a_temporary_failure_will_be_retried(): void
    {
        [$teacher, $submission, $answers] = $this->scenario();
        GradingRun::query()->create([
            'answer_id' => $answers[0]->id,
            'idempotency_key' => str_repeat('r', 64),
            'status' => GradingRunStatus::RetryableFailed,
            'provider' => 'gemini',
            'model' => 'test-model',
            'prompt_version' => 1,
            'error_code' => 'ConnectionException',
            'error_message' => 'cURL error 28',
        ]);

        $this->actingAs($teacher)->getJson(route('teacher.grading.ai-status', $submission))
            ->assertOk()
            ->assertJsonPath('state', 'retrying')
            ->assertJsonPath('message', 'Falha temporária. Uma nova tentativa espaçada foi agendada.');
    }

    public function test_status_check_cancels_an_ai_request_that_exceeded_the_limit(): void
    {
        config(['ai_grading.queue_timeout_seconds' => 30]);
        [$teacher, $submission, $answers] = $this->scenario();
        $submission->update(['status' => SubmissionStatus::Processing]);
        $run = GradingRun::query()->create([
            'answer_id' => $answers[0]->id,
            'idempotency_key' => str_repeat('t', 64),
            'status' => GradingRunStatus::Processing,
            'provider' => 'openai',
            'model' => 'test-model',
            'prompt_version' => 1,
        ]);
        $run->forceFill(['created_at' => now()->subSeconds(31)])->save();

        $this->actingAs($teacher)->getJson(route('teacher.grading.ai-status', $submission))
            ->assertOk()
            ->assertJsonPath('state', 'failed')
            ->assertJsonPath('errors.0.message', 'Correção cancelada porque ultrapassou o limite de 30 segundos.');

        $this->assertSame('AiGradingTimeout', $run->fresh()->error_code);
        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->status);
    }

    public function test_teacher_can_request_ai_for_only_one_answer(): void
    {
        Queue::fake();
        config(['services.openai.key' => 'test-key']);
        [$teacher, $submission, $answers] = $this->scenario();

        $response = $this->actingAs($teacher)->post(route('teacher.grading.ai-answer', [$submission, $answers[0]]));

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('grading_runs', ['answer_id' => $answers[0]->id]);
        $this->assertDatabaseMissing('grading_runs', ['answer_id' => $answers[1]->id]);
        Queue::assertPushed(GenerateAiSuggestion::class, 1);
    }

    public function test_changing_activity_prompt_creates_a_new_idempotent_run(): void
    {
        Queue::fake();
        config(['services.openai.key' => 'test-key']);
        [, $submission, $answers] = $this->scenario();
        $action = app(DispatchAiGradingAction::class);

        $firstRun = $action->execute($answers[0]);
        $firstRun->update(['status' => GradingRunStatus::Succeeded, 'finished_at' => now()]);
        $this->travel(301)->seconds();
        $submission->activity->update(['grading_instructions' => 'Dê prioridade à fundamentação.']);
        $secondRun = $action->execute($answers[0]->fresh());

        $this->assertNotSame($firstRun->idempotency_key, $secondRun->idempotency_key);
        $this->assertDatabaseCount('grading_runs', 2);
        Queue::assertPushed(GenerateAiSuggestion::class, 2);
    }

    public function test_failed_correction_can_be_requested_again_after_cooldown_and_old_attempt_is_removed(): void
    {
        Queue::fake();
        [$teacher, $submission, $answers] = $this->scenario();
        $action = app(DispatchAiGradingAction::class);
        $firstRun = $action->execute($answers[0]);
        $firstRun->attemptRecords()->create([
            'number' => 1, 'provider' => $firstRun->provider, 'model' => $firstRun->model,
            'status' => 'failed', 'reason' => 'invalid_response', 'started_at' => now(), 'finished_at' => now(),
        ]);
        $firstRun->update(['status' => GradingRunStatus::PermanentlyFailed, 'finished_at' => now(), 'error_code' => 'invalid_response']);

        try {
            $action->execute($answers[0]);
            $this->fail('A nova solicitação deve aguardar o intervalo de segurança.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('Aguarde 5 minuto(s)', $exception->getMessage());
        }
        $this->assertDatabaseCount('grading_runs', 1);
        Queue::assertPushed(GenerateAiSuggestion::class, 1);

        $this->travel(301)->seconds();
        $secondRun = $action->execute($answers[0]);

        $this->assertNotSame($firstRun->id, $secondRun->id);
        $this->assertSame($firstRun->idempotency_key, $secondRun->idempotency_key);
        $this->assertDatabaseMissing('grading_runs', ['id' => $firstRun->id]);
        $this->assertDatabaseCount('grading_attempts', 0);
        $this->assertDatabaseCount('grading_runs', 1);
        Queue::assertPushed(GenerateAiSuggestion::class, 2);
    }

    public function test_changing_options_cannot_bypass_active_run_or_cooldown(): void
    {
        Queue::fake();
        [, , $answers] = $this->scenario();
        $action = app(DispatchAiGradingAction::class);
        $firstRun = $action->execute($answers[0]);
        $otherOptions = ['feedback_detail' => 'detailed', 'intelligence_profile' => 'advanced'];

        try {
            $action->execute($answers[0], $otherOptions);
            $this->fail('Não deve haver duas correções ativas para a mesma questão.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('em andamento', $exception->getMessage());
        }

        $firstRun->update(['status' => GradingRunStatus::PermanentlyFailed, 'finished_at' => now()]);
        try {
            $action->execute($answers[0], $otherOptions);
            $this->fail('Mudar opções não deve contornar o intervalo de segurança.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('Aguarde 5 minuto(s)', $exception->getMessage());
        }

        $this->assertDatabaseCount('grading_runs', 1);
        Queue::assertPushed(GenerateAiSuggestion::class, 1);
    }

    public function test_ai_request_without_api_key_returns_to_manual_correction(): void
    {
        Queue::fake();
        config(['services.openai.key' => null, 'services.gemini.key' => null]);
        [$teacher, $submission] = $this->scenario();

        $this->actingAs($teacher)->post(route('teacher.grading.ai-all', $submission))
            ->assertRedirect()
            ->assertSessionHasErrors('ai');

        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->status);
        $this->assertDatabaseCount('grading_runs', 0);
        Queue::assertNothingPushed();
    }

    public function test_teacher_cannot_request_ai_for_an_answer_from_another_submission(): void
    {
        Queue::fake();
        config(['services.openai.key' => 'test-key']);
        [$teacher, $submission] = $this->scenario();
        [, , $otherAnswers] = $this->scenario();

        $this->actingAs($teacher)->post(route('teacher.grading.ai-answer', [$submission, $otherAnswers[0]]))
            ->assertNotFound();

        $this->assertDatabaseCount('grading_runs', 0);
    }

    public function test_activity_preferences_are_inherited_and_bulk_override_is_snapshotted(): void
    {
        Queue::fake();
        [$teacher, $submission, $answers] = $this->scenario();
        $submission->activity->update(['feedback_detail' => 'short', 'intelligence_profile' => 'economy', 'grading_strictness' => 'strict']);
        $this->actingAs($teacher)->post(route('teacher.grading.ai-answer', [$submission, $answers[0]]))->assertSessionHasNoErrors();
        $run = $answers[0]->fresh()->latestGradingRun;
        $this->assertSame('short', $run->request_snapshot['feedbackDetail']);
        $this->assertSame('economy', $run->request_snapshot['intelligenceProfile']);
        $this->assertSame('strict', $run->request_snapshot['gradingStrictness']);
        $this->assertStringContainsString('Rigorosa:', $run->request_snapshot['systemInstruction']);
        $this->assertStringContainsString('no máximo 30 palavras', $run->request_snapshot['systemInstruction']);
        $run->update(['status' => GradingRunStatus::Succeeded, 'finished_at' => now()]);
        $this->travel(301)->seconds();
        $this->post(route('teacher.grading.ai-activity', $submission->activity), ['feedback_detail' => 'detailed', 'intelligence_profile' => 'advanced', 'grading_strictness' => 'flexible'])->assertSessionHasNoErrors();
        foreach ($answers as $answer) {
            $this->assertSame('detailed', $answer->fresh()->latestGradingRun->request_snapshot['feedbackDetail']);
            $this->assertSame('advanced', $answer->fresh()->latestGradingRun->request_snapshot['intelligenceProfile']);
            $this->assertSame('flexible', $answer->fresh()->latestGradingRun->request_snapshot['gradingStrictness']);
            $this->assertStringContainsString('Flexível:', $answer->fresh()->latestGradingRun->request_snapshot['systemInstruction']);
        }
        $this->assertSame('short', $submission->activity->fresh()->feedback_detail);
        $this->assertSame('strict', $submission->activity->fresh()->grading_strictness);
    }

    public function test_private_feedback_is_only_visible_to_the_owning_teacher_not_in_json_or_student_result(): void
    {
        Queue::fake();
        [$teacher, $submission, $answers] = $this->scenario();
        $run = app(DispatchAiGradingAction::class)->execute($answers[0]);
        $run->update(['status' => GradingRunStatus::Succeeded]);
        $run->suggestion()->create(['score' => 4, 'criterion_scores' => [], 'evidence' => [], 'feedback' => 'Mensagem pública',
            'teacher_feedback' => 'SEGREDO-PEDAGOGICO', 'confidence' => 0.8, 'warnings' => [], 'created_at' => now()]);
        $this->actingAs($teacher)->get(route('teacher.grading.show', $submission))->assertOk()->assertSee('SEGREDO-PEDAGOGICO');
        $this->getJson(route('teacher.grading.ai-status', $submission))->assertOk()->assertDontSee('SEGREDO-PEDAGOGICO');
        $this->actingAs(User::factory()->teacher()->create())->get(route('teacher.grading.show', $submission))->assertForbidden();
        $this->getJson(route('teacher.grading.ai-status', $submission))->assertForbidden();
        $submission->update(['status' => SubmissionStatus::Released, 'released_at' => now()]);
        $this->actingAs($submission->student)->get(route('student.submissions.result', $submission))->assertOk()->assertDontSee('SEGREDO-PEDAGOGICO');
        $this->getJson(route('teacher.grading.ai-status', $submission))->assertForbidden();
    }

    public function test_invalid_profile_is_rejected_without_queueing(): void
    {
        Queue::fake();
        [$teacher, $submission] = $this->scenario();
        $this->actingAs($teacher)->post(route('teacher.grading.ai-all', $submission), ['intelligence_profile' => 'unknown'])->assertSessionHasErrors('intelligence_profile');
        Queue::assertNothingPushed();
    }

    public function test_invalid_grading_strictness_is_rejected_without_queueing(): void
    {
        Queue::fake();
        [$teacher, $submission] = $this->scenario();
        $this->actingAs($teacher)->post(route('teacher.grading.ai-all', $submission), ['grading_strictness' => 'unknown'])
            ->assertSessionHasErrors('grading_strictness');
        Queue::assertNothingPushed();
    }

    public function test_selected_batch_is_scoped_and_idempotent(): void
    {
        Queue::fake();
        [$teacher, $submission] = $this->scenario();
        $url = route('teacher.grading.ai-selected', $submission->activity);
        $data = ['submission_ids' => [$submission->public_id], 'feedback_detail' => 'short', 'intelligence_profile' => 'balanced'];
        $this->actingAs($teacher)->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('grading_runs', 2);
        $this->post($url, $data)->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, '1 ignoradas'));
        $this->assertDatabaseCount('grading_runs', 2);
        Queue::assertPushed(GenerateAiSuggestion::class, 2);
        $this->actingAs(User::factory()->teacher()->create())->post($url, $data)->assertForbidden();
        $this->actingAs(User::factory()->student()->create())->post($url, $data)->assertForbidden();
    }

    public function test_selected_batch_rejects_foreign_ids_before_dispatching_anything(): void
    {
        Queue::fake();
        [$teacher, $submission] = $this->scenario();
        [, $foreign] = $this->scenario();
        $url = route('teacher.grading.ai-selected', $submission->activity);
        $this->actingAs($teacher)->post($url, ['submission_ids' => [$submission->public_id, $foreign->public_id]])->assertSessionHasErrors('submission_ids.1');
        $this->post($url, ['submission_ids' => []])->assertSessionHasErrors('submission_ids');
        $this->post($url, ['submission_ids' => [$submission->public_id, $submission->public_id]])->assertSessionHasErrors('submission_ids.0');
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('grading_runs', 0);
    }

    public function test_selected_batch_rechecks_state_after_page_was_loaded(): void
    {
        Queue::fake();
        [$teacher, $submission] = $this->scenario();
        $this->actingAs($teacher)->get(route('teacher.activities.show', $submission->activity))->assertOk()->assertSee('data-delivery-select', false);
        $submission->update(['status' => SubmissionStatus::Reviewed]);
        $this->post(route('teacher.grading.ai-selected', $submission->activity), ['submission_ids' => [$submission->public_id]])->assertSessionHas('status', fn ($s) => str_contains($s, '1 ignoradas'));
        Queue::assertNothingPushed();
    }

    private function scenario(): array
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $activity = Activity::query()->create([
            'teacher_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'title' => 'Atividade dissertativa',
            'status' => ActivityStatus::Grading,
            'deadline_at' => now()->subHour(),
            'total_score' => 10,
        ]);
        $submission = Submission::query()->create([
            'activity_id' => $activity->id,
            'student_id' => $student->id,
            'status' => SubmissionStatus::Submitted,
            'submitted_at' => now(),
        ]);
        $answers = collect([1, 2])->map(function (int $position) use ($activity, $submission) {
            $question = ActivityQuestion::query()->create([
                'activity_id' => $activity->id,
                'type' => QuestionType::Essay,
                'body' => 'Questão '.$position,
                'expected_answer' => 'Resposta esperada '.$position,
                'max_score' => 5,
                'rubric_snapshot' => [['label' => 'Clareza', 'description' => 'Texto claro.', 'weight' => 5]],
                'position' => $position,
            ]);

            return $submission->answers()->create([
                'activity_question_id' => $question->id,
                'response_text' => 'Resposta do aluno '.$position,
                'version' => 1,
            ]);
        })->all();

        return [$teacher, $submission, $answers];
    }
}
