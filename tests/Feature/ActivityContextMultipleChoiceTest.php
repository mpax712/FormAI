<?php

namespace Tests\Feature;

use App\Application\Actions\ReviewSubmissionAction;
use App\Domain\Activities\Models\Activity;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Submissions\Enums\SubmissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityContextMultipleChoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_and_multiple_choice_work_through_publication_submission_and_review(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $classroom->students()->attach($student);

        $this->actingAs($teacher)->post(route('teacher.activities.store'), [
            'classroom_id' => $classroom->id,
            'title' => 'Avaliação com texto de apoio',
            'intent' => 'publish',
            'questions' => [
                ['type' => 'context', 'body' => "Leia o texto.\n\nObserve os dados abaixo.", 'max_score' => 99],
                ['type' => 'multiple_choice', 'body' => "Selecione duas opções.\nCada linha importa.", 'max_score' => 5,
                    'correct_options' => [0, 2], 'options' => [
                        ['text' => 'Primeira'], ['text' => 'Segunda'], ['text' => 'Terceira'], ['text' => 'Quarta'],
                    ]],
            ],
        ])->assertSessionHasNoErrors();

        $activity = Activity::firstOrFail();
        [$context, $choice] = $activity->questions->all();
        $this->assertSame(QuestionType::Context, $context->type);
        $this->assertSame(0.0, (float) $context->max_score);
        $this->assertSame(5.0, (float) $activity->total_score);
        $this->assertSame(['A', 'C'], collect($choice->options_snapshot)->filter(fn ($option) => $option['is_correct'])->pluck('key')->all());

        $this->actingAs($student)->get(route('student.activities.show', $activity))
            ->assertOk()->assertSee('Leia o texto.')->assertSee('Observe os dados abaixo.')
            ->assertSee('name="selected_option_keys[]"', false);
        $submission = $activity->submissions()->firstOrFail();
        $this->actingAs($student)->putJson(route('student.answers.save', [$submission, $context]), ['version' => 0])->assertStatus(409);
        $this->actingAs($student)->putJson(route('student.answers.save', [$submission, $choice]), [
            'version' => 0, 'selected_option_keys' => ['C', 'A'],
        ])->assertOk();
        $this->actingAs($student)->post(route('student.submissions.submit', $submission))->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->status);
        $this->assertSame(5.0, (float) $submission->fresh()->objective_score);

        app(ReviewSubmissionAction::class)->execute($submission, $teacher, []);
        $this->assertSame(5.0, (float) $submission->fresh()->final_score);
        $this->actingAs($teacher)->post(route('teacher.grading.release', $submission))->assertSessionHasNoErrors();
        $this->actingAs($student)->get(route('student.submissions.result', $submission))
            ->assertOk()->assertSee('Texto de apoio')->assertSee('Sua escolha · correta');
        $this->actingAs($teacher)->get(route('teacher.grading.show', $submission))
            ->assertOk()->assertSee('Correção automática')->assertSee('A. Primeira; C. Terceira');

        $otherStudent = User::factory()->student()->create();
        $classroom->students()->attach($otherStudent);
        $this->actingAs($otherStudent)->get(route('student.activities.show', $activity))->assertOk();
        $otherSubmission = $activity->submissions()->where('student_id', $otherStudent->id)->firstOrFail();
        $this->actingAs($otherStudent)->putJson(route('student.answers.save', [$otherSubmission, $choice]), [
            'version' => 0, 'selected_option_keys' => ['Z'],
        ])->assertStatus(409);
        $this->actingAs($otherStudent)->putJson(route('student.answers.save', [$otherSubmission, $choice]), [
            'version' => 0, 'selected_option_keys' => ['A', 'B'],
        ])->assertOk();
        $this->actingAs($otherStudent)->post(route('student.submissions.submit', $otherSubmission))->assertSessionHasNoErrors();
        $this->assertSame(0.0, (float) $otherSubmission->fresh()->objective_score);
    }

    public function test_context_only_and_multiple_choice_with_one_correct_option_are_rejected(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $base = ['classroom_id' => $classroom->id, 'title' => 'Teste', 'intent' => 'publish'];

        $this->actingAs($teacher)->post(route('teacher.activities.store'), $base + [
            'questions' => [['type' => 'context', 'body' => 'Somente contexto']],
        ])->assertSessionHasErrors('questions');

        $this->actingAs($teacher)->post(route('teacher.activities.store'), $base + [
            'questions' => [[
                'type' => 'multiple_choice', 'body' => 'Escolha duas.', 'max_score' => 2,
                'correct_options' => [0], 'options' => [['text' => 'Um'], ['text' => 'Dois']],
            ]],
        ])->assertSessionHasErrors('questions.0.options');
    }
}
