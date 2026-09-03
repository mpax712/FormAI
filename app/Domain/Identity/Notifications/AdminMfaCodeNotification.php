<?php

namespace App\Domain\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminMfaCodeNotification extends Notification
{
    use Queueable;
    public function __construct(private readonly string $code) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Codigo de acesso administrativo - FormAI')
            ->line('Use o codigo abaixo para concluir seu acesso administrativo.')
            ->line($this->code)->line('O codigo expira em 10 minutos. Se nao foi voce, altere sua senha.');
    }
}
