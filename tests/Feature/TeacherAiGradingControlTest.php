<?php

namespace Tests\Feature;

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
    use RefreshDatabase;

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

    public function test_status_check_cancels_an_ai_request_that_exceeded_the_limit(): void
    {
        config(['formai.grading_timeout_seconds' => 30]);
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

    public function test_ai_request_without_api_key_returns_to_manual_correction(): void
    {
        Queue::fake();
        config(['services.openai.key' => null]);
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
                'rubric_snapshot' => [['label' => 'Clareza', 'description' => 'Texto claro.', 'weight' => 1]],
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
