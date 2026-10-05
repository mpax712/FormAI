<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Tests\TestCase;

class FortifyAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_authenticate(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'password',
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_domain_user_supports_fortify_two_factor_authentication(): void
    {
        $this->assertContains(TwoFactorAuthenticatable::class, class_uses_recursive(User::class));
    }
}
