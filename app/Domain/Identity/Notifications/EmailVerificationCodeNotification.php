<?php

namespace App\Domain\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresInMinutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Seu código de verificação do FormAI')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Use o código abaixo para confirmar seu endereço de e-mail:')
            ->line($this->code)
            ->line('O código expira em '.$this->expiresInMinutes.' minutos e só pode ser usado uma vez.')
            ->line('Se você não solicitou esta confirmação, ignore esta mensagem.');
    }
}
