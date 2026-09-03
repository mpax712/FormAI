<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MfaController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        return $request->session()->has('pre_mfa_user_id') ? view('auth.mfa') : redirect()->route('login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = User::query()->find($request->session()->get('pre_mfa_user_id'));
        if (! $user || ! $user->mfa_expires_at?->isFuture() || ! Hash::check($data['code'], (string) $user->mfa_code_hash)) {
            throw ValidationException::withMessages(['code' => 'Codigo invalido ou expirado.']);
        }
        Auth::login($user, (bool) $request->session()->pull('pre_mfa_remember', false));
        $request->session()->forget('pre_mfa_user_id');
        $request->session()->regenerate();
        $user->forceFill(['mfa_code_hash' => null, 'mfa_expires_at' => null])->save();
        return redirect()->intended(route('dashboard'));
    }
}
