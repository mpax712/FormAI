<?php

namespace App\Http\Controllers;

use App\Application\Services\EmailVerificationCodeService;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmailVerificationCodeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email', ['user' => $request->user()]);
    }

    public function verify(Request $request, EmailVerificationCodeService $codes): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ], [
            'code.required' => 'Digite o código enviado ao seu e-mail.',
            'code.regex' => 'O código deve conter exatamente 6 números.',
        ]);

        $result = DB::transaction(function () use ($request, $data, $codes): array {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);

            if ($user->hasVerifiedEmail()) {
                return ['status' => 'verified', 'user' => $user];
            }

            if (! $user->email_verification_code_hash ||
                ! $user->email_verification_code_expires_at ||
                $user->email_verification_code_expires_at->isPast()) {
                return ['status' => 'expired'];
            }

            $maximumAttempts = config('formai.email_verification_code_attempts');
            if ($user->email_verification_code_attempts >= $maximumAttempts) {
                $this->invalidate($user);
                return ['status' => 'limited'];
            }

            if (! hash_equals($user->email_verification_code_hash, $codes->hash($data['code']))) {
                $attempts = $user->email_verification_code_attempts + 1;
                $user->forceFill(['email_verification_code_attempts' => $attempts])->save();

                if ($attempts >= $maximumAttempts) {
                    $this->invalidate($user);
                    return ['status' => 'limited'];
                }

                return ['status' => 'incorrect'];
            }

            $user->forceFill([
                'email_verified_at' => now(),
                'email_verification_code_hash' => null,
                'email_verification_code_expires_at' => null,
                'email_verification_code_attempts' => 0,
            ])->save();

            return ['status' => 'verified', 'user' => $user];
        });

        if ($result['status'] !== 'verified') {
            $message = match ($result['status']) {
                'expired' => 'Este código expirou. Solicite um novo código.',
                'limited' => 'O limite de tentativas foi atingido. Solicite um novo código.',
                default => 'Código incorreto. Confira o e-mail e tente novamente.',
            };
            throw ValidationException::withMessages(['code' => $message]);
        }

        $verified = $result['user'];
        if ($verified->wasChanged('email_verified_at')) {
            event(new Verified($verified));
        }

        return redirect()->intended(route('dashboard'))->with('status', 'E-mail confirmado com sucesso.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Enviamos um novo código. Use apenas o mais recente.');
    }

    private function invalidate(User $user): void
    {
        $user->forceFill([
            'email_verification_code_hash' => null,
            'email_verification_code_expires_at' => null,
            'email_verification_code_attempts' => 0,
        ])->save();
    }
}
