<?php

namespace Tests\Feature;

use App\Application\Actions\PublishActivityAction;
use App\Application\Actions\SaveActivityDraftAction;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivitySnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_without_ai_specifications_stays_disabled_when_published(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $question = Question::create(['owner_id' => $teacher->id, 'type' => 'essay', 'body' => 'Explique.', 'expected_answer' => 'Gabarito privado', 'teacher_instruction' => 'Valorize exemplos.', 'max_score' => 1, 'is_active' => true]);
        $question->rubricCriteria()->create(['label' => 'Clareza', 'description' => 'Clareza', 'weight' => 1, 'position' => 1]);
        $activity = app(SaveActivityDraftAction::class)->execute(null, $teacher, [
            'classroom_id' => $classroom->id, 'title' => 'Sem critérios importados',
            'bank_questions' => [$question->id], 'bank_correction' => [$question->id => false],
        ]);
        $published = app(PublishActivityAction::class)->execute($activity);
        $snapshot = $published->questions->first();
        $this->assertFalse($snapshot->import_correction);
        $this->assertNull($snapshot->expected_answer);
        $this->assertNull($snapshot->teacher_instruction);
        $this->assertSame([], $snapshot->rubric_snapshot);
        $this->actingAs($teacher)->get(route('teacher.activities.edit', $published))->assertOk()->assertSee('Usar avaliação geral');
    }

    public function test_publishing_copies_question_and_rubric_immutably(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma A', 'is_active' => true]);
        $question = Question::query()->create(['owner_id' => $teacher->id, 'type' => QuestionType::Essay, 'body' => 'Explique fotossintese.', 'expected_answer' => 'Conversao de energia luminosa.', 'max_score' => 10, 'is_active' => true]);
        $question->rubricCriteria()->create(['label' => 'Conceito', 'description' => 'Explica o processo', 'weight' => 1, 'position' => 1]);
        $activity = app(SaveActivityDraftAction::class)->execute(null, $teacher, [
            'classroom_id' => $classroom->id,
            'title' => 'Biologia',
            'description' => null,
            'deadline_at' => now()->addDay(),
            'bank_questions' => [$question->id],
            'questions' => [],
        ]);

        $published = app(PublishActivityAction::class)->execute($activity);
        $question->update(['body' => 'Texto alterado depois.']);

        $this->assertSame(ActivityStatus::Published, $published->status);
        $this->assertSame('Explique fotossintese.', $published->questions->first()->body);
        $this->assertSame('Conceito', $published->questions->first()->rubric_snapshot[0]['label']);
        $this->assertEquals(10.0, (float) $published->total_score);
    }
}
