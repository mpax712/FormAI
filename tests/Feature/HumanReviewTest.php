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
            ->assertSee('Revisar e publicar')
            ->assertSee('Entrega 1 de 1');

        $this->actingAs($teacher)->put(route('teacher.grading.review', $submission), [
            'grades' => [$answer->id => ['score' => 11, 'feedback' => 'Nota inválida.']],
        ])->assertSessionHasErrors('grades');
        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->status);
        $this->assertDatabaseMissing('grading_decisions', ['answer_id' => $answer->id]);

        $this->actingAs($teacher)->put(route('teacher.grading.review', $submission), [
            'grades' => [$answer->id => ['score' => 7.5, 'feedback' => 'Desenvolva a conclusao.']],
        ])->assertSessionHasNoErrors();
        $published = $submission->fresh();
        $this->assertSame(SubmissionStatus::Released, $published->status);
        $this->assertEquals(7.5, (float) $published->final_score);
        $this->assertNotNull($published->reviewed_at);
        $this->assertNotNull($published->released_at);
        $this->assertDatabaseHas('grading_decisions', ['answer_id' => $answer->id, 'feedback' => 'Desenvolva a conclusao.']);

        $this->actingAs($teacher)->get(route('teacher.grading.show', $submission))
            ->assertOk()->assertSee('Salvar e republicar');
    }

    public function test_teacher_can_edit_and_republish_a_published_result(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $classroom->students()->attach($student);
        $activity = Activity::query()->create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'title' => 'Redação', 'status' => ActivityStatus::Released, 'total_score' => 10]);
        $question = ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::Essay, 'body' => 'Explique.', 'max_score' => 10, 'position' => 1]);
        $submission = Submission::query()->create([
            'activity_id' => $activity->id, 'student_id' => $student->id,
            'status' => SubmissionStatus::Released, 'objective_score' => 0,
            'final_score' => 7, 'reviewed_at' => now()->subDay(), 'released_at' => now()->subDay(),
        ]);
        $answer = $submission->answers()->create(['activity_question_id' => $question->id, 'response_text' => 'Minha resposta.']);
        $answer->gradingDecision()->create(['reviewer_id' => $teacher->id, 'score' => 7, 'feedback' => 'Primeiro feedback.', 'confirmed_at' => now()->subDay()]);

        $this->actingAs($teacher)->get(route('teacher.activities.show', $activity))
            ->assertOk()->assertSee('Editar e republicar');
        $this->actingAs($teacher)->get(route('teacher.grading.show', $submission))
            ->assertOk()->assertSee('Salvar e republicar')->assertSee('Primeiro feedback.');

        $this->actingAs($otherTeacher)->put(route('teacher.grading.review', $submission), [
            'grades' => [$answer->id => ['score' => 9, 'feedback' => 'Sem permissão.']],
        ])->assertForbidden();
        $this->actingAs($teacher)->put(route('teacher.grading.review', $submission), [
            'grades' => [$answer->id => ['score' => 11, 'feedback' => 'Inválido.']],
        ])->assertSessionHasErrors('grades');
        $this->assertEquals(7, (float) $submission->fresh()->final_score);

        $this->actingAs($teacher)->put(route('teacher.grading.review', $submission), [
            'grades' => [$answer->id => ['score' => 9, 'feedback' => 'Feedback atualizado.']],
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Nota e feedback republicados para o aluno.');

        $this->assertSame(SubmissionStatus::Released, $submission->fresh()->status);
        $this->assertSame(ActivityStatus::Released, $activity->fresh()->status);
        $this->assertEquals(9, (float) $submission->fresh()->final_score);
        $this->assertDatabaseHas('grading_decisions', ['answer_id' => $answer->id, 'score' => 9, 'feedback' => 'Feedback atualizado.']);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $teacher->id, 'event' => 'submission.grade_republished']);
        $this->actingAs($student)->get(route('student.submissions.result', $submission))
            ->assertOk()->assertSee('9,00 / 10,00')->assertSee('Feedback atualizado.')->assertDontSee('Primeiro feedback.');

        $this->actingAs($teacher)->put(route('teacher.grading.review', $submission), [
            'grades' => [$answer->id => ['score' => 9, 'feedback' => 'Apenas o feedback mudou.']],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(9, (float) $submission->fresh()->final_score);
        $this->actingAs($student)->get(route('student.submissions.result', $submission))
            ->assertOk()->assertSee('Apenas o feedback mudou.')->assertDontSee('Feedback atualizado.');
    }

    public function test_teacher_can_override_objective_score_and_feedback_without_double_counting(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $classroom->students()->attach($student);
        $activity = Activity::query()->create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'title' => 'Prova', 'status' => ActivityStatus::Released, 'total_score' => 4]);
        $question = ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::SingleChoice, 'body' => 'Escolha.', 'max_score' => 4, 'position' => 1, 'options_snapshot' => [
            ['key' => 'A', 'text' => 'Correta', 'is_correct' => true],
            ['key' => 'B', 'text' => 'Outra', 'is_correct' => false],
        ]]);
        $submission = Submission::query()->create(['activity_id' => $activity->id, 'student_id' => $student->id, 'status' => SubmissionStatus::Released, 'objective_score' => 0, 'final_score' => 0, 'released_at' => now()]);
        $answer = $submission->answers()->create(['activity_question_id' => $question->id, 'selected_option_key' => 'B']);

        $this->actingAs($teacher)->get(route('teacher.grading.show', $submission))
            ->assertOk()->assertSee('Correção automática: 0,00 / 4,00 pontos.');
        $this->actingAs($teacher)->put(route('teacher.grading.review', $submission), [
            'grades' => [$answer->id => ['score' => 2, 'feedback' => 'Crédito parcial pela justificativa.']],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(2, (float) $submission->fresh()->final_score);
        $this->actingAs($student)->get(route('student.submissions.result', $submission))
            ->assertOk()->assertSee('Você errou')->assertSee('Nota ajustada pelo professor')->assertSee('2,00 / 4,00 pontos')->assertSee('Crédito parcial pela justificativa.');

        $this->actingAs($teacher)->put(route('teacher.grading.review', $submission), [
            'grades' => [$answer->id => ['score' => 3, 'feedback' => 'Nota revista.']],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(3, (float) $submission->fresh()->final_score);
        $this->assertSame(0.0, (float) $submission->fresh()->objective_score);

        $correctSubmission = Submission::query()->create(['activity_id' => $activity->id, 'student_id' => User::factory()->student()->create()->id, 'status' => SubmissionStatus::Released, 'objective_score' => 4, 'final_score' => 4, 'released_at' => now()]);
        $correctAnswer = $correctSubmission->answers()->create(['activity_question_id' => $question->id, 'selected_option_key' => 'A']);
        $this->actingAs($teacher)->put(route('teacher.grading.review', $correctSubmission), [
            'grades' => [$correctAnswer->id => ['score' => 2, 'feedback' => 'Nota ajustada.']],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(2, (float) $correctSubmission->fresh()->final_score);
        $this->assertSame(4.0, (float) $correctSubmission->fresh()->objective_score);
    }

    public function test_teacher_can_republish_selected_results_of_an_activity_atomically(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $students = User::factory()->student()->count(2)->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $classroom->students()->attach($students->pluck('id'));
        $activity = Activity::query()->create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'title' => 'Redações', 'status' => ActivityStatus::Released, 'total_score' => 10]);
        $question = ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::Essay, 'body' => 'Escreva.', 'max_score' => 10, 'position' => 1]);
        $submissions = $students->map(function ($student, $index) use ($activity, $question, $teacher) {
            $submission = Submission::query()->create(['activity_id' => $activity->id, 'student_id' => $student->id, 'status' => SubmissionStatus::Released, 'final_score' => $index + 3, 'released_at' => now()]);
            $answer = $submission->answers()->create(['activity_question_id' => $question->id, 'response_text' => 'Resposta.']);
            $answer->gradingDecision()->create(['reviewer_id' => $teacher->id, 'score' => $index + 3, 'feedback' => 'Feedback inicial.', 'confirmed_at' => now()]);

            return [$submission, $answer];
        });
        [[$first, $firstAnswer], [$second, $secondAnswer]] = $submissions->all();
        $url = route('teacher.grading.activity-results.update', $activity);
        $payload = [
            'selected' => [$first->public_id, $second->public_id],
            'grades' => [
                $first->public_id => [$firstAnswer->id => ['score' => 5, 'feedback' => 'Revisado um.']],
                $second->public_id => [$secondAnswer->id => ['score' => 11, 'feedback' => 'Revisado dois.']],
            ],
        ];

        $this->actingAs($otherTeacher)->get(route('teacher.grading.activity-results.edit', $activity))->assertForbidden();
        $this->actingAs($otherTeacher)->put($url, $payload)->assertForbidden();
        $this->actingAs($teacher)->get(route('teacher.grading.activity-results.edit', $activity))
            ->assertOk()->assertSee($students[0]->name)->assertSee($students[1]->name)->assertSee('Republicar selecionados');
        $this->actingAs($teacher)->put($url, $payload)->assertSessionHasErrors('grades');
        $this->assertEquals(3, (float) $first->fresh()->final_score);
        $this->assertEquals(4, (float) $second->fresh()->final_score);

        $payload['grades'][$second->public_id][$secondAnswer->id]['score'] = 6;
        $this->actingAs($teacher)->put($url, $payload)->assertSessionHasNoErrors();
        $this->assertEquals(5, (float) $first->fresh()->final_score);
        $this->assertEquals(6, (float) $second->fresh()->final_score);
        $this->assertSame(2, \App\Domain\Administration\Models\AuditLog::query()->where('event', 'submission.grade_republished')->count());
        $this->actingAs($students[1])->get(route('student.submissions.result', $second))
            ->assertOk()->assertSee('Revisado dois.');

        $payload['selected'] = [$first->public_id];
        $payload['grades'][$first->public_id][$firstAnswer->id] = ['score' => 7, 'feedback' => 'Só o primeiro.'];
        $payload['grades'][$second->public_id][$secondAnswer->id]['score'] = 'inválido';
        $this->actingAs($teacher)->put($url, $payload)->assertSessionHasNoErrors();
        $this->assertEquals(7, (float) $first->fresh()->final_score);
        $this->assertEquals(6, (float) $second->fresh()->final_score);
    }

    public function test_review_navigation_stays_within_owned_activity(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $activity = Activity::query()->create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'title' => 'Prova', 'status' => ActivityStatus::Grading, 'total_score' => 1]);
        $first = Submission::query()->create(['activity_id' => $activity->id, 'student_id' => User::factory()->student()->create()->id, 'status' => SubmissionStatus::Submitted, 'submitted_at' => now()->subMinute()]);
        $second = Submission::query()->create(['activity_id' => $activity->id, 'student_id' => User::factory()->student()->create()->id, 'status' => SubmissionStatus::Submitted, 'submitted_at' => now()]);
        Submission::query()->create(['activity_id' => $activity->id, 'student_id' => User::factory()->student()->create()->id, 'status' => SubmissionStatus::Draft]);

        $this->actingAs($teacher)->get(route('teacher.grading.show', $first))
            ->assertOk()->assertSee('Entrega 1 de 2')->assertSee(route('teacher.grading.show', $second), false);
        $this->actingAs($teacher)->get(route('teacher.grading.show', $second))
            ->assertOk()->assertSee('Entrega 2 de 2')->assertSee(route('teacher.grading.show', $first), false);
        $this->actingAs($otherTeacher)->get(route('teacher.grading.show', $first))->assertForbidden();
    }

    public function test_publishing_one_result_keeps_other_students_activity_open(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $classroom->students()->attach([$student->id, $otherStudent->id]);
        $activity = Activity::query()->create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'title' => 'Prova', 'status' => ActivityStatus::Grading, 'deadline_at' => now()->addHour(), 'total_score' => 1]);
        $question = ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::Essay, 'body' => 'Explique.', 'max_score' => 1, 'position' => 1]);
        $first = Submission::query()->create(['activity_id' => $activity->id, 'student_id' => $student->id, 'status' => SubmissionStatus::Submitted, 'submitted_at' => now()]);
        $firstAnswer = $first->answers()->create(['activity_question_id' => $question->id, 'response_text' => 'Primeira resposta.']);
        $second = Submission::query()->create(['activity_id' => $activity->id, 'student_id' => $otherStudent->id, 'status' => SubmissionStatus::Draft]);
        $second->answers()->create(['activity_question_id' => $question->id, 'response_text' => 'Segunda resposta.']);

        $this->actingAs($teacher)->put(route('teacher.grading.review', $first), [
            'grades' => [$firstAnswer->id => ['score' => 1, 'feedback' => 'Certo.']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::Released, $first->fresh()->status);
        $this->assertSame(ActivityStatus::Grading, $activity->fresh()->status);

        $this->actingAs($otherStudent)->post(route('student.submissions.submit', $second))->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::Submitted, $second->fresh()->status);
    }
}
