<?php

namespace Tests\Feature;

use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeacherReadCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_cached_questions_remain_isolated_and_are_invalidated_after_archive(): void
    {
        $owner = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();
        $question = Question::create(['owner_id' => $owner->id, 'type' => 'essay', 'body' => 'Private question unique marker', 'expected_answer' => 'Secret solution marker', 'max_score' => 1]);
        $url = route('teacher.questions.index');
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Private question unique marker')->assertDontSee('Secret solution marker');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get($url)->assertOk()->assertSee('Private question unique marker');
        $questionReads = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "questions"'));
        $this->assertCount(0, $questionReads, 'Uma listagem em cache não deve reler a tabela de questões.');
        DB::disableQueryLog();
        $this->actingAs($other)->get($url)->assertOk()->assertDontSee('Private question unique marker');
        $this->getJson(route('teacher.questions.api'))->assertOk()->assertJsonPath('data', []);
        $this->actingAs($owner)->getJson(route('teacher.questions.api'))->assertOk()
            ->assertJsonPath('data.0.body', 'Private question unique marker')
            ->assertJsonMissingPath('data.0.expected_answer')->assertJsonMissingPath('data.0.teacher_instruction');
        $this->actingAs($owner)->delete(route('teacher.questions.destroy', $question))->assertRedirect();
        $this->get($url)->assertOk()->assertDontSee('Private question unique marker');
    }

    public function test_statistics_api_is_authenticated_and_teacher_scoped(): void
    {
        $url = route('teacher.statistics');
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->student()->create())->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->teacher()->create())->getJson($url)->assertOk()->assertJsonPath('activities', [])->assertJsonPath('tokens.input', 0);
    }

    public function test_apis_reject_injection_shaped_filters_and_keep_other_teachers_data_private(): void
    {
        $owner = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();
        Question::create(['owner_id' => $other->id, 'type' => 'essay', 'body' => 'Foreign private marker', 'expected_answer' => 'Secret solution', 'max_score' => 1]);
        $this->actingAs($owner)->getJson(route('teacher.questions.api', ['q' => "' OR 1=1 --"]))
            ->assertOk()->assertJsonPath('data', [])->assertDontSee('Foreign private marker');
        $this->getJson(route('teacher.questions.api', ['page' => '1 UNION SELECT *']))->assertUnprocessable();
        $this->getJson(route('teacher.statistics', ['period' => "30' OR 1=1 --"]))->assertUnprocessable();
        $this->getJson(route('teacher.statistics', ['classroom' => "' OR 1=1 --"]))->assertUnprocessable();
        $this->getJson(route('teacher.statistics', ['summary' => '1; DROP TABLE users']))->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_summary_api_returns_only_aggregates_and_isolated_counts(): void
    {
        $owner = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();
        $classroom = Classroom::create(['teacher_id' => $other->id, 'name' => 'Private class', 'is_active' => true]);
        $classroom->activities()->create(['teacher_id' => $other->id, 'title' => 'Private activity', 'status' => 'published', 'published_at' => now()]);
        $this->getJson(route('teacher.statistics', ['summary' => 1]))->assertUnauthorized();
        $this->actingAs(User::factory()->student()->create())->getJson(route('teacher.statistics', ['summary' => 1]))->assertForbidden();
        $this->actingAs($owner)->getJson(route('teacher.statistics', ['summary' => 1]))->assertOk()
            ->assertExactJson(['summary' => ['activities' => 0, 'awaiting_review' => 0, 'published' => 0]])
            ->assertDontSee('Private class')->assertDontSee('Private activity');
    }

    public function test_statistics_count_students_who_have_not_started_and_exclude_other_teachers(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $students = User::factory()->student()->count(3)->create();
        foreach ($students as $student) {
            $classroom->members()->attach($student->id, ['status' => 'approved']);
        }
        $activity = $classroom->activities()->create(['teacher_id' => $teacher->id, 'title' => 'Minha atividade', 'status' => 'published', 'published_at' => now()]);
        $submission = $activity->submissions()->create(['student_id' => $students[0]->id, 'status' => 'submitted', 'submitted_at' => now()]);
        $question = $activity->questions()->create(['type' => 'essay', 'body' => 'Pergunta', 'max_score' => 1, 'position' => 1]);
        $answer = $submission->answers()->create(['activity_question_id' => $question->id, 'response_text' => 'Resposta']);
        $activity->submissions()->create(['student_id' => $students[1]->id, 'status' => 'draft']);
        $this->actingAs($teacher)->getJson(route('teacher.statistics'))->assertOk()
            ->assertJsonPath('activities.0.delivered', 1)->assertJsonPath('activities.0.pending', 2)->assertJsonPath('activities.0.awaiting_review', 1)->assertJsonPath('ai.not_requested', 1);
        $answer->gradingRuns()->create(['idempotency_key' => hash('sha256', 'stats'), 'status' => 'succeeded', 'provider' => 'gemini', 'model' => 'test-model', 'prompt_version' => 1, 'input_tokens' => 100, 'output_tokens' => 20]);
        $this->travel(31)->seconds();
        $this->getJson(route('teacher.statistics'))->assertOk()->assertJsonPath('ai.succeeded', 1)->assertJsonPath('ai.not_requested', 0)->assertJsonPath('tokens.input', 100)->assertJsonPath('tokens.output', 20);
        $this->get(route('dashboard'))->assertOk()->assertSee('Desempenho e participação');
        $this->actingAs(User::factory()->teacher()->create())->getJson(route('teacher.statistics'))->assertJsonPath('activities', []);
    }
}
