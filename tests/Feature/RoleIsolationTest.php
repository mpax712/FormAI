<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_access_teacher_or_admin_areas(): void
    {
        $student = User::factory()->student()->create();
        $this->actingAs($student)->get(route('teacher.classrooms.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_teacher_cannot_access_admin_area(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_public_landing_page_renders_security_headers(): void
    {
        $this->get(route('home'))->assertOk()->assertHeader('X-Frame-Options', 'DENY')->assertSee('Correção assistida');
    }
}
