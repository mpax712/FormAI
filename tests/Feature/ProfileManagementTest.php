<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_update_name_and_email(): void
    {
        $teacher = User::factory()->teacher()->create(['email' => 'professor@gmail.com']);

        $this->actingAs($teacher)->patch(route('profile.update'), [
            'name' => 'Novo Nome',
            'email' => 'novo.professor@gmail.com',
        ])->assertRedirect(route('verification.notice'));

        $teacher->refresh();
        $this->assertSame('Novo Nome', $teacher->name);
        $this->assertSame('novo.professor@gmail.com', $teacher->email);
        $this->assertNull($teacher->email_verified_at);
    }

    public function test_student_cannot_update_name_but_can_update_email(): void
    {
        $student = User::factory()->student()->create(['name' => 'Nome Acadêmico', 'email' => 'aluno@gmail.com']);

        $this->actingAs($student)->patch(route('profile.update'), [
            'name' => 'Nome Alterado',
            'email' => 'outro.aluno@gmail.com',
        ])->assertSessionHasErrors('name');

        $this->assertSame('Nome Acadêmico', $student->fresh()->name);

        $this->actingAs($student)->patch(route('profile.update'), [
            'email' => 'outro.aluno@gmail.com',
        ])->assertRedirect(route('verification.notice'));

        $this->assertSame('outro.aluno@gmail.com', $student->fresh()->email);
    }

    public function test_user_can_update_password(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->put(route('profile.password'), [
            'current_password' => 'password',
            'password' => 'nova-senha-123',
            'password_confirmation' => 'nova-senha-123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('nova-senha-123', $teacher->fresh()->password));
    }

    public function test_user_can_upload_profile_photo(): void
    {
        Storage::fake('avatars');
        $teacher = User::factory()->teacher()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $avatar = UploadedFile::fake()->createWithContent('avatar.png', $png);

        $this->actingAs($teacher)->post(route('profile.avatar'), ['avatar' => $avatar])
            ->assertSessionHasNoErrors();

        $path = $teacher->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('avatars')->assertExists($path);
    }
}
