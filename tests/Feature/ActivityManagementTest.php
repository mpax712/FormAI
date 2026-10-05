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

class ActivityManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_combines_title_and_classroom_filters_and_counts_missing_students(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma Alfa', 'is_active' => true]);
        $otherClassroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma Beta', 'is_active' => true]);
        $foreignClassroom = Classroom::query()->create(['teacher_id' => $otherTeacher->id, 'name' => 'Turma Externa', 'is_active' => true]);

        $students = User::factory()->student()->count(4)->create();
        $classroom->students()->attach($students->take(3)->pluck('id'));
        $classroom->members()->attach($students[3]->id, ['status' => 'pending']);

        $target = $this->activity($teacher, $classroom, 'Avaliação de História');
        $this->activity($teacher, $otherClassroom, 'Avaliação de Ciências');
        $this->activity($otherTeacher, $foreignClassroom, 'Avaliação de História externa');
        Submission::query()->create(['activity_id' => $target->id, 'student_id' => $students[0]->id, 'status' => SubmissionStatus::Submitted, 'submitted_at' => now()]);
        Submission::query()->create(['activity_id' => $target->id, 'student_id' => $students[1]->id, 'status' => SubmissionStatus::Draft]);

        $this->actingAs($teacher)->get(route('teacher.activities.index', ['q' => 'História', 'classroom_id' => $classroom->id]))
            ->assertOk()
            ->assertSee('Avaliação de História')
            ->assertDontSee('Avaliação de Ciências')
            ->assertDontSee('História externa')
            ->assertSee('Ainda não entregues')
            ->assertViewHas('activities', function ($activities) use ($target): bool {
                $activity = $activities->first();

                return $activities->total() === 1
                    && $activity->is($target)
                    && (int) $activity->delivered_count === 1
                    && (int) $activity->classroom->students_count === 3;
            });
    }

    public function test_activity_with_submission_only_accepts_general_metadata_changes(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $activity = $this->activity($teacher, $classroom, 'Título inicial');
        $question = ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::Essay, 'body' => 'Pergunta original', 'max_score' => 10, 'rubric_snapshot' => [['label' => 'Conteúdo', 'description' => '', 'weight' => 10]], 'position' => 1]);
        Submission::query()->create(['activity_id' => $activity->id, 'student_id' => $student->id, 'status' => SubmissionStatus::Draft]);

        $this->actingAs($teacher)->get(route('teacher.activities.edit', $activity))
            ->assertOk()->assertSee('Edição de dados gerais')->assertSee('Perguntas preservadas');

        $payload = [
            'classroom_id' => $classroom->id,
            'title' => 'Título atualizado',
            'description' => 'Descrição atualizada',
            'grading_instructions' => 'Valorize argumentos bem fundamentados.',
            'deadline_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'intent' => 'metadata',
        ];
        $this->actingAs($teacher)->put(route('teacher.activities.update', $activity), $payload)->assertSessionHasNoErrors();

        $this->assertSame('Título atualizado', $activity->fresh()->title);
        $this->assertSame('Valorize argumentos bem fundamentados.', $activity->fresh()->grading_instructions);
        $this->assertSame('Pergunta original', $question->fresh()->body);
        $this->assertDatabaseCount('submissions', 1);

        $payload['questions'] = [['type' => 'essay', 'body' => 'Pergunta forjada', 'max_score' => 10]];
        $this->actingAs($teacher)->put(route('teacher.activities.update', $activity), $payload)
            ->assertSessionHasErrors('questions');
        $this->assertSame('Pergunta original', $question->fresh()->body);
    }

    public function test_owner_can_delete_activity_with_related_data_and_other_teacher_cannot(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $activity = $this->activity($teacher, $classroom, 'Excluir atividade');
        $question = ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::Essay, 'body' => 'Pergunta', 'max_score' => 5, 'rubric_snapshot' => [], 'position' => 1]);
        $submission = Submission::query()->create(['activity_id' => $activity->id, 'student_id' => $student->id, 'status' => SubmissionStatus::Submitted, 'submitted_at' => now()]);
        $submission->answers()->create(['activity_question_id' => $question->id, 'response_text' => 'Resposta', 'version' => 1]);

        $this->actingAs($otherTeacher)->delete(route('teacher.activities.destroy', $activity))->assertForbidden();
        $this->assertDatabaseHas('activities', ['id' => $activity->id]);

        $this->actingAs($teacher)->delete(route('teacher.activities.destroy', $activity))
            ->assertRedirect(route('teacher.activities.index'));
        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
        $this->assertDatabaseCount('activity_questions', 0);
        $this->assertDatabaseCount('submissions', 0);
        $this->assertDatabaseCount('answers', 0);
    }

    private function activity(User $teacher, Classroom $classroom, string $title): Activity
    {
        return Activity::query()->create([
            'teacher_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'title' => $title,
            'status' => ActivityStatus::Published,
            'deadline_at' => now()->addDay(),
            'published_at' => now(),
        ]);
    }
}
