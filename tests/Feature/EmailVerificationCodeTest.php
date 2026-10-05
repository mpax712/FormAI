<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\EmailVerificationCodeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_user_receives_a_six_digit_code_and_can_verify_it(): void
    {
        Notification::fake();
        $user = User::factory()->teacher()->create(['email_verified_at' => null]);

        event(new Registered($user));

        $notification = Notification::sent($user, EmailVerificationCodeNotification::class)->first();
        $this->assertNotNull($notification);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $notification->code);
        $this->assertNotNull($user->fresh()->email_verification_code_hash);

        $this->actingAs($user)->get(route('verification.notice'))->assertOk()
            ->assertSee('Código de verificação')
            ->assertSee('autocomplete="one-time-code"', false);

        $this->post(route('verification.code.verify'), ['code' => $notification->code])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->email_verification_code_hash);
        $this->assertNull($user->email_verification_code_expires_at);
        $this->assertSame(0, $user->email_verification_code_attempts);
    }

    public function test_wrong_codes_are_counted_and_the_code_is_invalidated_at_the_limit(): void
    {
        Notification::fake();
        $user = User::factory()->teacher()->create(['email_verified_at' => null]);
        event(new Registered($user));
        $sentCode = Notification::sent($user, EmailVerificationCodeNotification::class)->first()->code;
        $wrongCode = $sentCode === '999999' ? '000000' : '999999';

        $this->actingAs($user);
        for ($attempt = 1; $attempt < config('formai.email_verification_code_attempts'); $attempt++) {
            $this->post(route('verification.code.verify'), ['code' => $wrongCode])
                ->assertSessionHasErrors('code');
            $this->assertSame($attempt, $user->fresh()->email_verification_code_attempts);
        }

        $this->post(route('verification.code.verify'), ['code' => $wrongCode])
            ->assertSessionHasErrors('code');
        $user->refresh();
        $this->assertNull($user->email_verification_code_hash);
        $this->assertNull($user->email_verified_at);
    }

    public function test_expired_code_is_rejected_and_resend_replaces_it(): void
    {
        Notification::fake();
        $user = User::factory()->student()->create(['email_verified_at' => null]);
        event(new Registered($user));
        $oldNotification = Notification::sent($user, EmailVerificationCodeNotification::class)->first();
        $user->forceFill(['email_verification_code_expires_at' => now()->subMinute()])->save();

        $this->actingAs($user)->post(route('verification.code.verify'), ['code' => $oldNotification->code])
            ->assertSessionHasErrors('code');
        $this->post(route('verification.send'))->assertRedirect()->assertSessionHas('status');

        $notifications = Notification::sent($user, EmailVerificationCodeNotification::class);
        $this->assertCount(2, $notifications);
        $newNotification = $notifications->last();
        $this->assertNotSame($oldNotification->code, $newNotification->code);
        $this->post(route('verification.code.verify'), ['code' => $oldNotification->code])->assertSessionHasErrors('code');
        $this->post(route('verification.code.verify'), ['code' => $newNotification->code])->assertRedirect(route('dashboard'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_code_format_and_authentication_are_required_and_link_route_is_gone(): void
    {
        $user = User::factory()->teacher()->create(['email_verified_at' => null]);
        $this->post(route('verification.code.verify'), ['code' => '123456'])->assertRedirect(route('login'));
        $this->actingAs($user)->post(route('verification.code.verify'), ['code' => '12A456'])->assertSessionHasErrors('code');
        $this->get('/email/verify/'.$user->id.'/hash')->assertNotFound();
    }
}
