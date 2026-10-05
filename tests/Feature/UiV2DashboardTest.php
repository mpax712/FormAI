<?php

namespace Tests\Feature;

use App\Domain\Activities\Models\Activity;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiV2DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function activity(User $teacher, array $attributes = []): Activity
    {
        $classroom = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'Turma '.$teacher->id, 'is_active' => true]);
        return $classroom->activities()->create(array_merge(['teacher_id' => $teacher->id, 'title' => 'Atividade', 'status' => 'published', 'published_at' => now(), 'total_score' => 10], $attributes));
    }

    private function submission(Activity $activity, string $status, ?float $score, ?User $student = null)
    {
        $student ??= User::factory()->student()->create();
        $activity->classroom->members()->syncWithoutDetaching([$student->id => ['status' => 'approved']]);
        return $activity->submissions()->create(['student_id' => $student->id, 'status' => $status, 'final_score' => $score, 'submitted_at' => now(), 'released_at' => $status === 'released' ? now() : null]);
    }

    public function test_performance_uses_only_published_non_null_grades_and_normalizes_maximum(): void
    {
        $teacher = User::factory()->teacher()->create();
        $activity = $this->activity($teacher);
        $this->submission($activity, 'released', 8);
        $this->submission($activity, 'released', null);
        $this->submission($activity, 'reviewed', 2);
        $this->submission($activity, 'submitted', 1);
        $large = $this->activity($teacher, ['total_score' => 20]);
        $this->submission($large, 'released', 10);
        $zero = $this->activity($teacher, ['total_score' => 0]);
        $this->submission($zero, 'released', 0);
        $empty = $this->activity($teacher);
        $other = $this->activity(User::factory()->teacher()->create(), ['title' => 'Privada de outro professor']);
        $this->submission($other, 'released', 10);
        $response = $this->actingAs($teacher)->getJson(route('teacher.statistics'))->assertOk();
        $rows = collect($response->json('activities'))->keyBy('id');
        $this->assertEquals(80, $rows[$activity->public_id]['average_percent']);
        $this->assertSame(1, $rows[$activity->public_id]['sample_count']);
        $this->assertSame(2, $rows[$activity->public_id]['published_count']);
        $this->assertEquals(50, $rows[$large->public_id]['average_percent']);
        $this->assertNull($rows[$zero->public_id]['average_percent']);
        $this->assertSame(0, $rows[$zero->public_id]['sample_count']);
        $this->assertNull($rows[$empty->public_id]['average_percent']);
        $this->assertArrayNotHasKey($other->public_id, $rows->all());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Correções da IA')->assertDontSee('Tokens informados')->assertSee('80,0%')->assertSee('Sem resultados publicados com nota');
    }

    public function test_filters_pagination_and_cache_are_scoped_to_teacher_and_filter(): void
    {
        $teacher = User::factory()->teacher()->create();
        $recent = $this->activity($teacher, ['title' => 'Recente']);
        $old = $this->activity($teacher, ['title' => 'Antiga', 'published_at' => now()->subDays(60)]);
        $foreign = $this->activity(User::factory()->teacher()->create());
        $this->actingAs($teacher)->getJson(route('teacher.statistics'))->assertJsonCount(1, 'activities');
        $this->getJson(route('teacher.statistics', ['period' => '90']))->assertJsonCount(2, 'activities');
        $this->getJson(route('teacher.statistics', ['period' => 'all', 'classroom' => $old->classroom->public_id]))->assertJsonPath('activities.0.title', 'Antiga');
        $this->getJson(route('teacher.statistics', ['classroom' => $foreign->classroom->public_id]))->assertUnprocessable();
        $this->getJson(route('teacher.statistics', ['period' => 'custom', 'from' => now()->subDays(61)->toDateString(), 'to' => now()->subDays(59)->toDateString()]))->assertJsonPath('activities.0.title', 'Antiga');
        $this->getJson(route('teacher.statistics', ['period' => 'custom']))->assertUnprocessable();
        $this->getJson(route('teacher.statistics', ['period' => 'custom', 'from' => '2026-09-10', 'to' => '2026-09-01']))->assertUnprocessable();
        for ($i = 0; $i < 11; $i++) $this->activity($teacher);
        $this->travel(31)->seconds();
        $this->getJson(route('teacher.statistics', ['page' => 1]))->assertJsonCount(10, 'activities')->assertJsonPath('pagination.total', 12)->assertJsonPath('summary.activities', 12);
        $this->getJson(route('teacher.statistics', ['page' => 2]))->assertJsonCount(2, 'activities');
        $this->get(route('dashboard', ['period' => 'all']))->assertSee('period=all', false);
    }

    public function test_student_panel_respects_membership_reopening_and_publication(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $expired = $this->activity($teacher, ['title' => 'Vencida', 'deadline_at' => now()->subDay()]);
        $this->submission($expired, 'draft', null, $student);
        $reopened = $this->activity($teacher, ['title' => 'Reaberta', 'deadline_at' => now()->subDay()]);
        $this->submission($reopened, 'draft', null, $student)->update(['reopened_until' => now()->addDay()]);
        $noDeadline = $this->activity($teacher, ['title' => 'Sem prazo', 'deadline_at' => null]);
        $noDeadline->classroom->members()->attach($student->id, ['status' => 'approved']);
        $hidden = $this->activity($teacher, ['title' => 'Turma não aprovada']);
        $hidden->classroom->members()->attach($student->id, ['status' => 'pending']);
        $reviewed = $this->activity($teacher, ['title' => 'Resultado ainda privado']);
        $this->submission($reviewed, 'reviewed', 9, $student);
        $released = $this->activity($teacher, ['title' => 'Resultado liberado']);
        $this->submission($released, 'released', 8, $student);
        $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertSee('Reaberta até')->assertSee('Continuar rascunho')->assertSee('Começar atividade')->assertSee('Vencida')->assertSee('Resultado liberado')->assertDontSee('Resultado ainda privado')->assertDontSee('Turma não aprovada')->assertSee('Entrada aguardando aprovação');
    }

    public function test_admin_panel_separates_waiting_from_failure_and_handles_missing_heartbeat(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Professores ativos')->assertSee('IA na fila / nova tentativa')->assertSee('Falhas definitivas da IA')->assertSee('Sem sinal recente');
    }
}
