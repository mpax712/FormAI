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
