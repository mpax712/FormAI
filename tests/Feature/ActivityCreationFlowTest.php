<?php

namespace Tests\Feature;

use App\Application\Actions\PublishActivityAction;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Models\ActivityQuestion;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityCreationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_page_prioritizes_direct_questions_and_offers_question_bank(): void
    {
        [$teacher] = $this->teacherAndClassroom();

        $this->actingAs($teacher)->get(route('teacher.activities.create'))
            ->assertOk()
            ->assertSee('Adicionar dissertativa')
            ->assertSee('Adicionar alternativa')
            ->assertSee('data-add-question="essay"', false)
            ->assertSee('data-add-question="single_choice"', false)
            ->assertSee('Visualizar')
            ->assertSee('questions[__INDEX__][expected_answer]', false)
            ->assertSee('questions[__INDEX__][rubric][0][label]', false)
            ->assertSee('name="grading_instructions"', false)
            ->assertSee('Como corrigir com IA')
            ->assertSee('Critérios de correção')
            ->assertDontSee('questions[__INDEX__][teacher_instruction]', false)
            ->assertSee('Usar banco de questões')
            ->assertSee('Criada somente para esta atividade');
    }

    public function test_teacher_can_publish_an_essay_without_expected_answer_or_rubric(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $payload = $this->activityPayload($classroom->id);
        $payload['intent'] = 'publish';
        $payload['feedback_detail'] = 'detailed';
        $payload['intelligence_profile'] = 'advanced';
        unset($payload['questions'][0]['expected_answer'], $payload['questions'][0]['rubric']);

        $response = $this->actingAs($teacher)->post(route('teacher.activities.store'), $payload);

        $activity = Activity::query()->firstOrFail();
        $response->assertRedirect(route('teacher.activities.show', $activity));
        $this->assertSame(ActivityStatus::Published, $activity->status);
        $this->assertSame('detailed', $activity->feedback_detail);
        $this->assertSame('advanced', $activity->intelligence_profile);
        $this->assertNull($activity->questions->first()->expected_answer);
        $this->assertSame([], $activity->questions->first()->rubric_snapshot);
    }

    public function test_teacher_can_save_a_draft_and_preview_the_student_experience(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $payload = $this->activityPayload($classroom->id);
        $payload['intent'] = 'preview';

        $response = $this->actingAs($teacher)->post(route('teacher.activities.store'), $payload);
        $activity = Activity::query()->firstOrFail();

        $response->assertRedirect(route('teacher.activities.preview', $activity));
        $this->assertSame(ActivityStatus::Draft, $activity->status);
        $this->assertNull($activity->questions()->firstOrFail()->teacher_instruction);

        $this->actingAs($teacher)->get(route('teacher.activities.preview', $activity))
            ->assertOk()
            ->assertSee('Modo de pré-visualização')
            ->assertSee('Atividade de teste')
            ->assertSee('Explique o conteúdo.')
            ->assertDontSee('Resposta esperada.');

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_another_teacher_cannot_open_the_activity_preview(): void
    {
        [$owner, $classroom] = $this->teacherAndClassroom();
        [$otherTeacher] = $this->teacherAndClassroom();
        $this->actingAs($owner)->post(route('teacher.activities.store'), $this->activityPayload($classroom->id));
        $activity = Activity::query()->firstOrFail();

        $this->actingAs($otherTeacher)->get(route('teacher.activities.preview', $activity))
            ->assertForbidden();
    }

    public function test_teacher_creates_and_publishes_activity_with_direct_essay_question(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();

        $response = $this->actingAs($teacher)->post(route('teacher.activities.store'), [
            'classroom_id' => $classroom->id,
            'title' => 'Atividade direta',
            'description' => 'Responda com atenção.',
            'deadline_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'intent' => 'publish',
            'questions' => [[
                'type' => 'essay',
                'body' => 'Explique o ciclo da água.',
                'expected_answer' => 'Evaporação, condensação e precipitação.',
                'max_score' => 10,
                'rubric' => [['label' => 'Conceito', 'description' => 'Explica as etapas', 'weight' => 10]],
            ]],
        ]);

        $activity = Activity::query()->firstOrFail();
        $response->assertRedirect(route('teacher.activities.show', $activity));
        $this->assertSame(ActivityStatus::Published, $activity->fresh()->status);
        $this->assertNull($activity->questions()->firstOrFail()->source_question_id);
        $this->assertSame('Explique o ciclo da água.', $activity->questions()->first()->body);
    }

    public function test_teacher_can_define_expected_answer_and_weighted_grading_criteria(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $payload = $this->activityPayload($classroom->id);
        $payload['questions'][0]['max_score'] = 10;
        $payload['grading_instructions'] = 'Considere respostas equivalentes e valorize a fundamentação.';
        $payload['questions'][0]['expected_answer'] = 'Uma resposta de referência completa.';
        $payload['questions'][0]['rubric'] = [
            ['label' => 'Conteúdo', 'description' => 'Domínio dos conceitos.', 'weight' => 6],
            ['label' => 'Clareza', 'description' => 'Organização e objetividade.', 'weight' => 4],
        ];

        $this->actingAs($teacher)->post(route('teacher.activities.store'), $payload)
            ->assertSessionHasNoErrors();

        $question = ActivityQuestion::query()->firstOrFail();
        $this->assertSame('Considere respostas equivalentes e valorize a fundamentação.', $question->activity->grading_instructions);
        $this->assertSame('Uma resposta de referência completa.', $question->expected_answer);
        $this->assertSame('Conteúdo', $question->rubric_snapshot[0]['label']);
        $this->assertEqualsWithDelta(6, $question->rubric_snapshot[0]['weight'], 0.001);
        $this->assertSame('Clareza', $question->rubric_snapshot[1]['label']);
        $this->assertEqualsWithDelta(4, $question->rubric_snapshot[1]['weight'], 0.001);
    }

    public function test_grading_criteria_points_must_total_question_score(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $payload = $this->activityPayload($classroom->id);
        $payload['questions'][0]['rubric'] = [
            ['label' => 'Conteúdo', 'description' => '', 'weight' => 0.5],
            ['label' => 'Clareza', 'description' => '', 'weight' => 0.2],
        ];

        $this->actingAs($teacher)->post(route('teacher.activities.store'), $payload)
            ->assertSessionHasErrors('questions.0.rubric');

        $this->assertDatabaseCount('activities', 0);

        $payload['questions'][0]['rubric'] = [
            ['label' => 'Conteúdo', 'description' => '', 'weight' => 0.8],
            ['label' => 'Clareza', 'description' => '', 'weight' => 0.4],
        ];
        $this->actingAs($teacher)->post(route('teacher.activities.store'), $payload)
            ->assertSessionHasErrors('questions.0.rubric');

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_teacher_can_publish_with_the_datetime_local_browser_format(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $payload = $this->activityPayload($classroom->id);
        $payload['intent'] = 'publish';
        $payload['deadline_at'] = now()->addDays(3)->format('Y-m-d\\TH:i');

        $response = $this->actingAs($teacher)->post(route('teacher.activities.store'), $payload);

        $response->assertSessionHasNoErrors();
        $activity = Activity::query()->firstOrFail();
        $this->assertSame(ActivityStatus::Published, $activity->status);
        $this->assertTrue($activity->deadline_at->isFuture());
    }

    public function test_teacher_can_publish_an_activity_without_a_deadline(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $payload = $this->activityPayload($classroom->id);
        $payload['intent'] = 'publish';
        unset($payload['deadline_at']);

        $response = $this->actingAs($teacher)->post(route('teacher.activities.store'), $payload);

        $response->assertSessionHasNoErrors();
        $activity = Activity::query()->firstOrFail();
        $this->assertSame(ActivityStatus::Published, $activity->status);
        $this->assertNull($activity->deadline_at);
        $this->actingAs($teacher)->get(route('teacher.activities.show', $activity))
            ->assertOk()
            ->assertSee('sem prazo de entrega');
    }

    public function test_teacher_can_create_activity_using_only_question_bank(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $bankQuestion = Question::query()->create([
            'owner_id' => $teacher->id,
            'type' => QuestionType::SingleChoice,
            'body' => 'Qual é a capital do Brasil?',
            'max_score' => 2,
            'is_active' => true,
        ]);
        $bankQuestion->options()->createMany([
            ['option_key' => 'A', 'text' => 'Brasília', 'is_correct' => true, 'position' => 1],
            ['option_key' => 'B', 'text' => 'São Paulo', 'is_correct' => false, 'position' => 2],
        ]);

        $this->actingAs($teacher)->post(route('teacher.activities.store'), [
            'classroom_id' => $classroom->id,
            'title' => 'Atividade do banco',
            'deadline_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'intent' => 'draft',
            'bank_questions' => [$bankQuestion->id],
        ])->assertSessionHasNoErrors();

        $activity = Activity::query()->firstOrFail();
        $this->assertSame($bankQuestion->id, $activity->questions()->firstOrFail()->source_question_id);
        $this->assertSame(ActivityStatus::Draft, $activity->status);
    }

    public function test_direct_single_choice_question_maps_selected_correct_option(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();

        $this->actingAs($teacher)->post(route('teacher.activities.store'), [
            'classroom_id' => $classroom->id,
            'title' => 'Questão objetiva direta',
            'deadline_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'intent' => 'draft',
            'questions' => [[
                'type' => 'single_choice',
                'body' => 'Quanto é dois mais dois?',
                'max_score' => 1,
                'correct_option' => 1,
                'options' => [['text' => '3'], ['text' => '4'], ['text' => '5']],
            ]],
        ])->assertSessionHasNoErrors();

        $options = Activity::query()->firstOrFail()->questions()->firstOrFail()->options_snapshot;
        $this->assertFalse($options[0]['is_correct']);
        $this->assertTrue($options[1]['is_correct']);
        $this->assertFalse($options[2]['is_correct']);
    }

    public function test_inactive_or_foreign_classroom_is_rejected(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $classroom->update(['is_active' => false]);
        [, $foreignClassroom] = $this->teacherAndClassroom();

        foreach ([$classroom, $foreignClassroom] as $invalidClassroom) {
            $this->actingAs($teacher)->post(route('teacher.activities.store'), $this->activityPayload($invalidClassroom->id))
                ->assertSessionHasErrors('classroom_id');
        }

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_foreign_or_inactive_bank_question_is_rejected(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        [$otherTeacher] = $this->teacherAndClassroom();
        $foreign = Question::query()->create(['owner_id' => $otherTeacher->id, 'type' => QuestionType::Essay, 'body' => 'Outra', 'expected_answer' => 'Resposta', 'max_score' => 1, 'is_active' => true]);
        $inactive = Question::query()->create(['owner_id' => $teacher->id, 'type' => QuestionType::Essay, 'body' => 'Inativa', 'expected_answer' => 'Resposta', 'max_score' => 1, 'is_active' => false]);

        foreach ([$foreign, $inactive] as $invalidQuestion) {
            $payload = $this->activityPayload($classroom->id);
            $payload['questions'] = [];
            $payload['bank_questions'] = [$invalidQuestion->id];
            $this->actingAs($teacher)->post(route('teacher.activities.store'), $payload)
                ->assertSessionHasErrors('bank_questions.0');
        }

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_malformed_nested_question_data_returns_validation_errors(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $payload = $this->activityPayload($classroom->id);
        $payload['questions'] = [[
            'type' => 'single_choice',
            'body' => 'Pergunta',
            'max_score' => 1,
            'correct_option' => 0,
            'options' => ['estrutura inválida'],
        ]];

        $this->actingAs($teacher)->post(route('teacher.activities.store'), $payload)
            ->assertSessionHasErrors('questions.0.options.0');
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_activity_rejects_more_than_fifty_combined_questions(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $bankQuestion = Question::query()->create(['owner_id' => $teacher->id, 'type' => QuestionType::Essay, 'body' => 'Banco', 'expected_answer' => 'Resposta', 'max_score' => 1, 'is_active' => true]);
        $payload = $this->activityPayload($classroom->id);
        $payload['questions'] = array_fill(0, 50, $payload['questions'][0]);
        $payload['bank_questions'] = [$bankQuestion->id];

        $this->actingAs($teacher)->post(route('teacher.activities.store'), $payload)
            ->assertSessionHasErrors('questions');
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_save_and_publish_rolls_back_when_publication_fails(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $this->app->instance(PublishActivityAction::class, new class extends PublishActivityAction
        {
            public function execute(Activity $activity): Activity
            {
                throw new \DomainException('Falha simulada na publicação.');
            }
        });
        $payload = $this->activityPayload($classroom->id);
        $payload['intent'] = 'publish';

        $this->actingAs($teacher)->post(route('teacher.activities.store'), $payload)
            ->assertSessionHasErrors('questions');
        $this->assertDatabaseCount('activities', 0);
        $this->assertDatabaseCount('activity_questions', 0);
    }

    public function test_draft_in_inactive_classroom_cannot_be_published(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $this->actingAs($teacher)->post(route('teacher.activities.store'), $this->activityPayload($classroom->id))
            ->assertSessionHasNoErrors();
        $activity = Activity::query()->firstOrFail();
        $classroom->update(['is_active' => false]);

        $this->actingAs($teacher)->post(route('teacher.activities.publish', $activity))
            ->assertSessionHasErrors('questions');
        $this->assertSame(ActivityStatus::Draft, $activity->fresh()->status);
    }

    public function test_draft_can_still_be_edited_when_its_current_classroom_becomes_inactive(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $this->actingAs($teacher)->post(route('teacher.activities.store'), $this->activityPayload($classroom->id))
            ->assertSessionHasNoErrors();
        $activity = Activity::query()->firstOrFail();
        $classroom->update(['is_active' => false]);

        $payload = $this->activityPayload($classroom->id);
        $payload['title'] = 'Rascunho revisado';

        $this->actingAs($teacher)->put(route('teacher.activities.update', $activity), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame('Rascunho revisado', $activity->fresh()->title);
        $this->assertSame(ActivityStatus::Draft, $activity->fresh()->status);
    }

    public function test_expired_draft_cannot_be_published_through_direct_publish_route(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $activity = Activity::query()->create([
            'teacher_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'title' => 'Prazo expirado',
            'status' => ActivityStatus::Draft,
            'deadline_at' => now()->subMinute(),
        ]);
        ActivityQuestion::query()->create([
            'activity_id' => $activity->id,
            'type' => QuestionType::Essay,
            'body' => 'Pergunta',
            'expected_answer' => 'Resposta',
            'max_score' => 1,
            'rubric_snapshot' => [['label' => 'Critério', 'description' => 'Descrição', 'weight' => 1]],
            'position' => 1,
        ]);

        $this->actingAs($teacher)->post(route('teacher.activities.publish', $activity))
            ->assertSessionHasErrors('questions');

        $this->assertSame(ActivityStatus::Draft, $activity->fresh()->status);
        $this->assertNull($activity->fresh()->published_at);
    }

    public function test_edit_displays_unavailable_imported_questions_for_explicit_removal(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $inactive = Question::query()->create(['owner_id' => $teacher->id, 'type' => QuestionType::Essay, 'body' => 'Pergunta desativada', 'expected_answer' => 'Resposta', 'max_score' => 1, 'is_active' => true]);
        $deleted = Question::query()->create(['owner_id' => $teacher->id, 'type' => QuestionType::Essay, 'body' => 'Pergunta excluída', 'expected_answer' => 'Resposta', 'max_score' => 1, 'is_active' => true]);
        $payload = $this->activityPayload($classroom->id);
        $payload['questions'] = [];
        $payload['bank_questions'] = [$inactive->id, $deleted->id];
        $this->actingAs($teacher)->post(route('teacher.activities.store'), $payload)->assertSessionHasNoErrors();
        $activity = Activity::query()->firstOrFail();
        $inactive->update(['is_active' => false]);
        $deleted->delete();

        $this->actingAs($teacher)->get(route('teacher.activities.edit', $activity))
            ->assertOk()
            ->assertSee('Pergunta desativada')
            ->assertSee('Pergunta excluída')
            ->assertSee('Indisponível — remova ou substitua');
        $this->actingAs($teacher)->post(route('teacher.activities.publish', $activity))
            ->assertSessionHasErrors('questions');
        $this->assertSame(ActivityStatus::Draft, $activity->fresh()->status);
    }

    public function test_published_activity_without_submissions_can_be_edited(): void
    {
        [$teacher, $classroom] = $this->teacherAndClassroom();
        $activity = Activity::query()->create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'title' => 'Publicada', 'status' => ActivityStatus::Published, 'deadline_at' => now()->addDay(), 'published_at' => now()]);
        ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::Essay, 'body' => 'Pergunta', 'expected_answer' => 'Resposta', 'max_score' => 1, 'rubric_snapshot' => [['label' => 'Critério', 'description' => 'Descrição', 'weight' => 1]], 'position' => 1]);

        $this->actingAs($teacher)->get(route('teacher.activities.edit', $activity))->assertOk();
        $this->actingAs($teacher)->put(route('teacher.activities.update', $activity), $this->activityPayload($classroom->id))
            ->assertSessionHasNoErrors();
    }

    public function test_question_bank_is_paginated_and_keeps_selected_items_visible(): void
    {
        [$teacher] = $this->teacherAndClassroom();
        $questions = collect(range(1, 25))->map(fn ($index) => Question::query()->create([
            'owner_id' => $teacher->id,
            'type' => QuestionType::Essay,
            'body' => 'Pergunta '.$index,
            'expected_answer' => 'Resposta',
            'max_score' => 1,
            'is_active' => true,
        ]));
        $selected = $questions->first();

        $this->actingAs($teacher)->get(route('teacher.activities.create', [
            'bank_selection' => 1,
            'bank_questions' => [$selected->id],
            'bank_q' => 'Pergunta',
        ]))->assertOk()
            ->assertSee($selected->body)
            ->assertViewHas('selectedBankQuestions', fn ($items) => $items->count() === 1)
            ->assertViewHas('bankQuestions', fn ($items) => $items->perPage() === 20 && $items->total() === 24 && $items->hasPages());
    }

    private function teacherAndClassroom(): array
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma A', 'is_active' => true]);

        return [$teacher, $classroom];
    }

    private function activityPayload(int $classroomId): array
    {
        return [
            'classroom_id' => $classroomId,
            'title' => 'Atividade de teste',
            'deadline_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'intent' => 'draft',
            'questions' => [[
                'type' => 'essay',
                'body' => 'Explique o conteúdo.',
                'expected_answer' => 'Resposta esperada.',
                'max_score' => 1,
                'rubric' => [['label' => 'Conceito', 'description' => 'Domínio do conceito', 'weight' => 1]],
            ]],
        ];
    }
}
