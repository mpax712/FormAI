<?php

namespace Tests\Feature;

use App\Application\Actions\ReviewSubmissionAction;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HumanReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_human_review_becomes_published_grade(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $activity = Activity::query()->create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'title' => 'Redacao', 'status' => ActivityStatus::Grading, 'deadline_at' => now()->subHour(), 'total_score' => 10]);
        $question = ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::Essay, 'body' => 'Argumente.', 'max_score' => 10, 'rubric_snapshot' => [['label' => 'Argumentacao', 'weight' => 1]], 'position' => 1]);
        $submission = Submission::query()->create(['activity_id' => $activity->id, 'student_id' => $student->id, 'status' => SubmissionStatus::Submitted, 'submitted_at' => now()]);
        $answer = $submission->answers()->create(['activity_question_id' => $question->id, 'response_text' => 'Minha argumentacao.', 'version' => 1]);
        $run = GradingRun::query()->create([
            'answer_id' => $answer->id,
            'idempotency_key' => str_repeat('a', 64),
            'status' => GradingRunStatus::Succeeded,
            'provider' => 'openai',
            'model' => 'gpt-5.6-terra',
            'prompt_version' => 1,
        ]);
        $run->suggestion()->create([
            'score' => 8,
            'criterion_scores' => [['criterion' => 'Argumentacao', 'score' => 8, 'justification' => 'Boa estrutura.']],
            'evidence' => ['Minha argumentacao.'],
            'feedback' => 'Sugestão inicial da IA.',
            'confidence' => .85,
            'warnings' => [],
            'created_at' => now(),
        ]);

        $this->assertTrue(Route::has('teacher.grading.ai-all'));
        $this->assertTrue(Route::has('teacher.grading.ai-answer'));
        $this->actingAs($teacher)->get(route('teacher.activities.show', $activity))
            ->assertOk()
            ->assertSee('Corrigir manualmente')
            ->assertSee('Sugestões prontas');
        $this->actingAs($teacher)->get(route('teacher.grading.show', $submission))
            ->assertOk()
            ->assertSee('Correção manual')
            ->assertSee('Sugestão da IA')
            ->assertSee('Sugestão inicial da IA.')
            ->assertSee('value="8.00"', false)
            ->assertDontSee('Gerar sugestões com IA')
            ->assertSee('Salvar revisão humana');

        $reviewed = app(ReviewSubmissionAction::class)->execute($submission, $teacher, [$answer->id => ['score' => 7.5, 'feedback' => 'Desenvolva a conclusao.']]);
        $this->assertSame(SubmissionStatus::Reviewed, $reviewed->status);
        $this->assertEquals(7.5, (float) $reviewed->final_score);
        $this->assertNull($reviewed->released_at);

        $this->actingAs($teacher)->post(route('teacher.grading.release', $reviewed))->assertRedirect();
        $this->assertSame(SubmissionStatus::Released, $reviewed->fresh()->status);
    }
}
