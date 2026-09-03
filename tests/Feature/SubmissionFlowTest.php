<?php

namespace Tests\Feature;

use App\Application\Actions\SaveAnswerAction;
use App\Application\Actions\SubmitActivityAction;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Models\ActivityQuestion;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Submissions\Enums\SubmissionStatus;
use App\Domain\Submissions\Models\Submission;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubmissionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_choice_is_scored_and_submission_is_final(): void
    {
        [$submission, $question] = $this->scenario();
        app(SaveAnswerAction::class)->execute($submission, $question, ['selected_option_key' => 'B', 'version' => 0]);
        $result = app(SubmitActivityAction::class)->execute($submission);
        $this->assertSame(SubmissionStatus::Submitted, $result->status);
        $this->assertEquals(5.0, (float) $result->objective_score);
        $this->expectException(DomainException::class);
        app(SaveAnswerAction::class)->execute($result, $question, ['selected_option_key' => 'A', 'version' => 1]);
    }

    public function test_stale_autosave_version_is_rejected(): void
    {
        [$submission, $question] = $this->scenario();
        app(SaveAnswerAction::class)->execute($submission, $question, ['selected_option_key' => 'A', 'version' => 0]);
        $this->expectException(DomainException::class);
        app(SaveAnswerAction::class)->execute($submission->fresh(), $question, ['selected_option_key' => 'B', 'version' => 0]);
    }

    public function test_essay_submission_does_not_dispatch_ai_grading_automatically(): void
    {
        Queue::fake();
        config(['services.openai.key' => 'test-key']);
        [$submission, $question] = $this->essayScenario();
        app(SaveAnswerAction::class)->execute($submission, $question, ['response_text' => 'Resposta do aluno.', 'version' => 0]);

        $result = app(SubmitActivityAction::class)->execute($submission);

        $this->assertSame(SubmissionStatus::Submitted, $result->status);
        $this->assertDatabaseCount('grading_runs', 0);
        Queue::assertNothingPushed();
    }

    public function test_essay_submission_remains_available_for_manual_review_without_api_key(): void
    {
        Queue::fake();
        config(['services.openai.key' => null]);
        [$submission, $question] = $this->essayScenario();
        app(SaveAnswerAction::class)->execute($submission, $question, ['response_text' => 'Resposta do aluno.', 'version' => 0]);

        $result = app(SubmitActivityAction::class)->execute($submission);

        $this->assertSame(SubmissionStatus::Submitted, $result->status);
        $this->assertDatabaseCount('grading_runs', 0);
        Queue::assertNothingPushed();
    }

    public function test_student_can_submit_an_activity_without_a_deadline(): void
    {
        config(['services.openai.key' => null]);
        [$submission, $question] = $this->essayScenario();
        $submission->activity()->update(['deadline_at' => null]);
        app(SaveAnswerAction::class)->execute($submission, $question, ['response_text' => 'Resposta sem prazo.', 'version' => 0]);

        $result = app(SubmitActivityAction::class)->execute($submission);

        $this->assertSame(SubmissionStatus::Submitted, $result->status);
        $this->assertNotNull($result->submitted_at);
    }

    public function test_autosave_rejects_an_option_that_does_not_belong_to_the_question(): void
    {
        [$submission, $question] = $this->scenario();

        try {
            app(SaveAnswerAction::class)->execute($submission, $question, ['selected_option_key' => 'Z', 'version' => 0]);
            $this->fail('Uma alternativa inválida deveria ser rejeitada.');
        } catch (DomainException $exception) {
            $this->assertSame('A alternativa selecionada não pertence a esta questão.', $exception->getMessage());
        }

        $this->assertDatabaseCount('answers', 0);
    }

    private function scenario(): array
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'T', 'is_active' => true]);
        $classroom->students()->attach($student);
        $activity = Activity::query()->create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'title' => 'A', 'status' => ActivityStatus::Published, 'deadline_at' => now()->addHour(), 'total_score' => 5]);
        $question = ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::SingleChoice, 'body' => '2+2?', 'max_score' => 5, 'options_snapshot' => [['key' => 'A', 'text' => '3', 'is_correct' => false], ['key' => 'B', 'text' => '4', 'is_correct' => true]], 'position' => 1]);
        $submission = Submission::query()->create(['activity_id' => $activity->id, 'student_id' => $student->id, 'status' => SubmissionStatus::Draft]);

        return [$submission, $question];
    }

    private function essayScenario(): array
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'T', 'is_active' => true]);
        $classroom->students()->attach($student);
        $activity = Activity::query()->create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'title' => 'Redação', 'status' => ActivityStatus::Published, 'deadline_at' => now()->addHour(), 'total_score' => 10]);
        $question = ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::Essay, 'body' => 'Explique.', 'max_score' => 10, 'rubric_snapshot' => [], 'position' => 1]);
        $submission = Submission::query()->create(['activity_id' => $activity->id, 'student_id' => $student->id, 'status' => SubmissionStatus::Draft]);

        return [$submission, $question];
    }
}
