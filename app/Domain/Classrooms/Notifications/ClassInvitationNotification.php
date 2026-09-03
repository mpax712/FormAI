<?php

namespace App\Domain\Classrooms\Notifications;

use App\Domain\Classrooms\Models\Classroom;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClassInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(private readonly Classroom $classroom, private readonly string $token) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Convite para turma no FormAI')
            ->line('Voce foi convidado para a turma '.$this->classroom->name.'.')
            ->action('Aceitar convite', route('invitations.accept', ['token' => $this->token]))
            ->line('O convite expira em sete dias e so pode ser usado pelo e-mail convidado.');
    }
}
