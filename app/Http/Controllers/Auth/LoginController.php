<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Notifications\AdminMfaCodeNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View { return view('auth.login'); }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Credenciais invalidas.']);
        }
        $user = $request->user();
        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Esta conta esta desativada.']);
        }
        if ($user->isAdmin()) {
            $code = (string) random_int(100000, 999999);
            $user->forceFill(['mfa_code_hash' => Hash::make($code), 'mfa_expires_at' => now()->addMinutes(10)])->save();
            $request->session()->put(['pre_mfa_user_id' => $user->id, 'pre_mfa_remember' => $request->boolean('remember')]);
            Auth::logout();
            $user->notify(new AdminMfaCodeNotification($code));
            return redirect()->route('mfa.challenge');
        }
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
