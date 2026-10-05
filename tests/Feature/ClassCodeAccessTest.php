<?php

namespace Tests\Feature;

use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassCodeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_toggle_automatic_entry_and_existing_requests_remain_pending(): void
    {
        $teacher = User::factory()->teacher()->create();
        $pendingStudent = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma 8A', 'is_active' => true]);
        $classroom->members()->attach($pendingStudent->id, ['status' => 'pending']);

        $this->assertFalse($classroom->fresh()->auto_approve_join);
        $initialPage = $this->actingAs($teacher)->get(route('teacher.classrooms.show', $classroom));
        $initialPage->assertOk()->assertSee('Autorizar entrada automaticamente');
        $this->assertDoesNotMatchRegularExpression('/id="auto-approve-join"[^>]*checked/', $initialPage->getContent());

        $this->patch(route('teacher.classrooms.auto-approve-join', $classroom), ['auto_approve_join' => '1'])
            ->assertRedirect(route('teacher.classrooms.show', $classroom));

        $this->assertTrue($classroom->fresh()->auto_approve_join);
        $this->assertDatabaseHas('classroom_memberships', [
            'classroom_id' => $classroom->id,
            'user_id' => $pendingStudent->id,
            'status' => 'pending',
        ]);
        $updatedPage = $this->get(route('teacher.classrooms.show', $classroom));
        $updatedPage->assertOk();
        $this->assertMatchesRegularExpression('/id="auto-approve-join"[^>]*checked/', $updatedPage->getContent());

        $this->patch(route('teacher.classrooms.auto-approve-join', $classroom), ['auto_approve_join' => '0'])
            ->assertRedirect(route('teacher.classrooms.show', $classroom));
        $this->assertFalse($classroom->fresh()->auto_approve_join);
    }

    public function test_only_the_classroom_teacher_can_change_automatic_entry(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma 8A', 'is_active' => true]);
        $route = route('teacher.classrooms.auto-approve-join', $classroom);

        $this->patch($route, ['auto_approve_join' => '1'])->assertRedirect(route('login'));
        $this->actingAs($otherTeacher)->patch($route, ['auto_approve_join' => '1'])->assertForbidden();
        $this->actingAs($student)->patch($route, ['auto_approve_join' => '1'])->assertForbidden();
        $this->assertFalse($classroom->fresh()->auto_approve_join);

        $this->actingAs($teacher)->patch($route, [])->assertSessionHasErrors('auto_approve_join');
        $this->assertFalse($classroom->fresh()->auto_approve_join);
    }

    public function test_new_student_is_approved_automatically_when_the_setting_is_enabled(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma 8A', 'is_active' => true]);
        $this->actingAs($teacher)->patch(route('teacher.classrooms.auto-approve-join', $classroom), ['auto_approve_join' => '1']);
        auth()->logout();

        $this->post(route('class-code.lookup'), ['code' => $classroom->join_code])->assertRedirect(route('class-code.register'));
        $this->get(route('class-code.register'))->assertOk()->assertSee('sem esperar a aprovação do professor');
        $this->post(route('class-code.store'), [
            'name' => 'Aluno Automático',
            'email' => 'aluno.automatico@gmail.com',
            'password' => 'segura123',
            'password_confirmation' => 'segura123',
            'age_band' => '13_17',
            'terms' => '1',
            'website' => '',
        ])->assertRedirect(route('verification.notice'));

        $student = User::query()->where('email', 'aluno.automatico@gmail.com')->firstOrFail();
        $this->assertDatabaseHas('classroom_memberships', [
            'classroom_id' => $classroom->id,
            'user_id' => $student->id,
            'status' => 'approved',
            'approved_by' => null,
        ]);
        $this->assertNotNull($classroom->students()->whereKey($student->id)->firstOrFail()->pivot->approved_at);
        $this->assertNull($student->email_verified_at);
        $this->get(route('student.activities.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_student_can_register_with_class_code_and_waits_for_teacher_approval(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma 8A', 'is_active' => true]);

        $this->post(route('class-code.lookup'), ['code' => strtolower($classroom->join_code)])
            ->assertRedirect(route('class-code.register'));

        $this->get(route('class-code.register'))->assertOk()->assertSee('Turma 8A');

        $response = $this->post(route('class-code.store'), [
            'name' => 'Aluno Exemplo',
            'email' => 'aluno.formai@gmail.com',
            'password' => 'segura123',
            'password_confirmation' => 'segura123',
            'age_band' => '13_17',
            'terms' => '1',
            'website' => '',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $student = User::query()->where('email', 'aluno.formai@gmail.com')->firstOrFail();
        $this->assertSame(UserRole::Student, $student->role);
        $this->assertAuthenticatedAs($student);
        $this->assertDatabaseHas('classroom_memberships', [
            'classroom_id' => $classroom->id,
            'user_id' => $student->id,
            'status' => 'pending',
        ]);
        $this->assertFalse($classroom->students()->whereKey($student->id)->exists());
    }

    public function test_classroom_teacher_can_approve_pending_student(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma 9B', 'is_active' => true]);
        $classroom->members()->attach($student->id, ['status' => 'pending']);

        $this->actingAs($teacher)->patch(route('teacher.classrooms.requests.approve', [$classroom, $student]))
            ->assertRedirect();

        $this->assertDatabaseHas('classroom_memberships', [
            'classroom_id' => $classroom->id,
            'user_id' => $student->id,
            'status' => 'approved',
            'approved_by' => $teacher->id,
        ]);
        $this->assertTrue($classroom->students()->whereKey($student->id)->exists());
    }

    public function test_another_teacher_cannot_approve_the_request(): void
    {
        $owner = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $owner->id, 'name' => 'Turma privada', 'is_active' => true]);
        $classroom->members()->attach($student->id, ['status' => 'pending']);

        $this->actingAs($otherTeacher)->patch(route('teacher.classrooms.requests.approve', [$classroom, $student]))
            ->assertForbidden();
    }
}
