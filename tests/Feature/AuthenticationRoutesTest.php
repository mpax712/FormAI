<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_authenticated_users_to_their_dashboard(): void
    {
        $this->get('/')->assertOk()->assertSee('Menos tempo corrigindo.');
        $this->actingAs(User::factory()->student()->create())->get('/')->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('Meu aprendizado');

        $this->actingAs(User::factory()->teacher()->create(['email_verified_at' => null]))
            ->get('/')->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_route_names_are_unique(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter();

        $this->assertSame([], $names->duplicates()->values()->all());
    }

    public function test_legacy_authentication_pages_redirect_to_fortify(): void
    {
        $this->get('/cadastro')->assertStatus(301)->assertRedirect('/register');
        $this->get('/entrar')->assertStatus(301)->assertRedirect('/login');
        $this->get('/senha/esqueci')->assertStatus(301)->assertRedirect('/forgot-password');
        $this->post('/cadastro')->assertMethodNotAllowed();
        $this->post('/entrar')->assertMethodNotAllowed();
    }

    public function test_login_form_posts_to_the_current_tunnel_origin(): void
    {
        $this->get('/login')->assertOk()->assertSee('<form method="post" action="/login">', false);
    }

    public function test_reset_password_page_receives_token_and_email(): void
    {
        $this->get('/reset-password/example-token?email=user%40example.com')
            ->assertOk()
            ->assertSee('example-token', false)
            ->assertSee('user@example.com', false);
    }
}
