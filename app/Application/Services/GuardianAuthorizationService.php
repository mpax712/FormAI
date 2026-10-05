<?php

namespace App\Application\Services;

use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\GuardianAuthorizationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class GuardianAuthorizationService
{
    public function request(User $user, string $email): void
    {
        $token = Str::random(64);
        GuardianAuthorization::query()->updateOrCreate(['user_id' => $user->id], [
            'guardian_email' => Str::lower($email),
            'guardian_name' => null,
            'token_hash' => hash('sha256', $token),
            'terms_version' => config('legal.terms_version'),
            'expires_at' => now()->addDays(config('legal.guardian_link_days')),
            'accepted_at' => null,
            'declined_at' => null,
        ]);

        Notification::route('mail', $email)->notify(new GuardianAuthorizationNotification($token, $user->name));
    }
}
