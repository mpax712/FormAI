<?php

namespace Tests\Feature;

use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Models\ActivityQuestion;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Submissions\Enums\SubmissionStatus;
use App\Domain\Submissions\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentResultExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_result_preserves_questions_and_every_option_with_answer_and_key(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $classroom->students()->attach($student);
        $activity = Activity::query()->create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'title' => 'Avaliação', 'status' => ActivityStatus::Released, 'total_score' => 8]);
        ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::Context, 'body' => 'Texto de apoio importante.', 'max_score' => 0, 'position' => 1]);
        $single = ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::SingleChoice, 'body' => 'Qual é a opção?', 'max_score' => 2, 'position' => 2, 'options_snapshot' => [
            ['key' => 'A', 'text' => 'Alternativa A', 'is_correct' => false],
            ['key' => 'B', 'text' => 'Alternativa B', 'is_correct' => true],
            ['key' => 'C', 'text' => 'Alternativa C', 'is_correct' => false],
        ]]);
        $multiple = ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::MultipleChoice, 'body' => 'Escolha duas.', 'max_score' => 2, 'position' => 3, 'options_snapshot' => [
            ['key' => 'A', 'text' => 'Primeira', 'is_correct' => true],
            ['key' => 'B', 'text' => 'Segunda', 'is_correct' => false],
            ['key' => 'C', 'text' => 'Terceira', 'is_correct' => true],
        ]]);
        $essay = ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::Essay, 'body' => 'Explique.', 'max_score' => 4, 'position' => 4]);
        $submission = Submission::query()->create(['activity_id' => $activity->id, 'student_id' => $student->id, 'status' => SubmissionStatus::Released, 'final_score' => 6, 'objective_score' => 2, 'released_at' => now()]);
        $submission->answers()->create(['activity_question_id' => $single->id, 'selected_option_key' => 'B']);
        $submission->answers()->create(['activity_question_id' => $multiple->id, 'selected_option_key' => 'A,B']);
        $essayAnswer = $submission->answers()->create(['activity_question_id' => $essay->id, 'response_text' => 'Minha explicação.']);
        $essayAnswer->gradingDecision()->create(['reviewer_id' => $teacher->id, 'score' => 4, 'feedback' => 'Boa explicação.', 'confirmed_at' => now()]);

        $response = $this->actingAs($student)->get(route('student.submissions.result', $submission));
        $response->assertOk()->assertSee('Texto de apoio importante.')
            ->assertSeeInOrder(['Qual é a opção?', 'Alternativa A', 'Alternativa B', 'Alternativa C', 'Escolha duas.', 'Primeira', 'Segunda', 'Terceira', 'Explique.'])
            ->assertSee('Você acertou')->assertSee('Você errou')
            ->assertSee('Sua escolha · incorreta')->assertSee('Resposta correta')
            ->assertSee('Minha explicação.')->assertSee('Boa explicação.')
            ->assertSee('student-question-card')->assertDontSee('data-autosave');

        $this->actingAs(User::factory()->student()->create())->get(route('student.submissions.result', $submission))->assertForbidden();
    }
}
