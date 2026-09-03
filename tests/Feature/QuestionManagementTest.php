<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_open_question_creation_form(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->get(route('teacher.questions.create'))
            ->assertOk()
            ->assertSee('Nova questão')
            ->assertSee('Alternativa D')
            ->assertDontSee('name="teacher_instruction"', false)
            ->assertSee('rubric[2][weight]', false);
    }

    public function test_teacher_creates_essay_with_normalized_rubric(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher)->post(route('teacher.questions.store'), [
            'type' => 'essay', 'body' => 'Explique o tema.', 'expected_answer' => 'Uma explicacao esperada.',
            'max_score' => 10,
            'rubric' => [['label' => 'Conceito', 'description' => 'Dominio conceitual', 'weight' => 0.7], ['label' => 'Clareza', 'description' => 'Texto claro', 'weight' => 0.3]],
            'options' => [['text' => '']],
        ])->assertRedirect(route('teacher.questions.index'));

        $question = Question::query()->firstOrFail();
        $this->assertSame(QuestionType::Essay, $question->type);
        $this->assertNull($question->teacher_instruction);
        $this->assertCount(2, $question->rubricCriteria);
        $this->assertCount(0, $question->options);
    }

    public function test_single_choice_requires_exactly_one_correct_option(): void
    {
        $teacher = User::factory()->teacher()->create();
        $response = $this->actingAs($teacher)->from(route('teacher.questions.create'))->post(route('teacher.questions.store'), [
            'type' => 'single_choice', 'body' => 'Qual?', 'max_score' => 2,
            'options' => [['text' => 'A', 'is_correct' => 1], ['text' => 'B', 'is_correct' => 1]],
        ]);
        $response->assertRedirect(route('teacher.questions.create'))->assertSessionHasErrors('options');
        $this->assertDatabaseCount('questions', 0);
    }
}
