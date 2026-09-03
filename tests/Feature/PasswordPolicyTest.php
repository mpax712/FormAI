<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_register_with_a_simple_six_character_password(): void
    {
        Event::fake([Registered::class]);

        $response = $this->post(route('register'), [
            'name' => 'Professora Teste',
            'email' => 'Professora@GMAIL.COM',
            'password' => 'abcdef',
            'password_confirmation' => 'abcdef',
            'terms' => '1',
            'website' => '',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $user = User::query()->where('email', 'professora@gmail.com')->firstOrFail();
        $this->assertTrue(Hash::check('abcdef', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_still_requires_six_characters(): void
    {
        $this->from(route('register'))->post(route('register'), [
            'name' => 'Professor Teste',
            'email' => 'professor@gmail.com',
            'password' => 'abcde',
            'password_confirmation' => 'abcde',
            'terms' => '1',
            'website' => '',
        ])->assertRedirect(route('register'))->assertSessionHasErrors('password');
    }
}
