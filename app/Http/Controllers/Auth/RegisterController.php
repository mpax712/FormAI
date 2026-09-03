<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View { return view('auth.register'); }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(config('formai.password_min_length'))],
            'terms' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ]);
        $user = User::query()->create($data + ['role' => UserRole::Teacher, 'is_active' => true]);
        event(new Registered($user));
        Auth::login($user);
        return redirect()->route('verification.notice');
    }
}
