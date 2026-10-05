<?php

namespace Tests\Unit;

use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Classrooms\Notifications\ClassInvitationNotification;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\EmailVerificationCodeNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_generate_verification_and_password_reset_notifications(): void
    {
        Notification::fake();

        $user = User::factory()->teacher()->create(['email' => 'professor@example.com', 'email_verified_at' => null]);
        $user->sendEmailVerificationNotification();
        $user->sendPasswordResetNotification('test-token');

        Notification::assertSentTo($user, EmailVerificationCodeNotification::class);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_verification_email_contains_the_code_without_an_action_link(): void
    {
        $user = User::factory()->teacher()->make(['name' => 'Professor']);
        $message = (new EmailVerificationCodeNotification('012345', 15))->toMail($user);

        $this->assertSame('Seu código de verificação do FormAI', $message->subject);
        $this->assertStringContainsString('012345', implode(' ', $message->introLines));
        $this->assertNull($message->actionUrl);
    }

    public function test_class_invitation_generates_an_action_link(): void
    {
        $classroom = new Classroom(['name' => 'Turma 8A']);
        $message = (new ClassInvitationNotification($classroom, 'invitation-token'))
            ->toMail(new AnonymousNotifiable);

        $this->assertSame('Convite para turma no FormAI', $message->subject);
        $this->assertStringContainsString('invitation-token', $message->actionUrl);
    }
}
