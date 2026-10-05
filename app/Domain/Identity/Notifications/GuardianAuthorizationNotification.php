<?php

namespace App\Domain\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class GuardianAuthorizationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token, private readonly string $studentName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('FormAI: autorização de acesso do aluno')
            ->greeting('Olá, responsável!')
            ->line('Foi solicitada uma conta no piloto escolar FormAI para '.$this->studentName.'.')
            ->line('Leia os Termos de Uso e Privacidade antes de decidir. O link expira em sete dias.')
            ->action('Analisar autorização', route('guardian.show', $this->token))
            ->line('Se você não conhece essa solicitação, pode ignorar esta mensagem. A conta não será liberada sem sua confirmação.');
    }
}
