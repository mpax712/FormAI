<?php

namespace App\Application\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\EmailVerificationCodeNotification;

class EmailVerificationCodeService
{
    public function send(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $hash = $this->hash($code);
        } while ($user->email_verification_code_hash && hash_equals($user->email_verification_code_hash, $hash));
        $minutes = config('formai.email_verification_code_minutes');

        $user->forceFill([
            'email_verification_code_hash' => $hash,
            'email_verification_code_expires_at' => now()->addMinutes($minutes),
            'email_verification_code_attempts' => 0,
        ])->save();

        $user->notify(new EmailVerificationCodeNotification($code, $minutes));
    }

    public function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
