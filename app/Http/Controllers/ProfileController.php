<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $rules = [
            'email' => ['required', 'email:rfc,dns', 'max:255', Rule::unique('users')->ignore($user->id)],
            'name' => $user->isTeacher()
                ? ['required', 'string', 'max:120']
                : ['prohibited'],
        ];
        $data = $request->validate($rules);
        $emailChanged = strcasecmp($data['email'], $user->email) !== 0;

        $user->email = $data['email'];
        if ($user->isTeacher()) {
            $user->name = $data['name'];
        }
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        if ($emailChanged) {
            event(new Registered($user));
            return redirect()->route('verification.notice')->with('status', 'E-mail atualizado. Confirme o novo endereço para continuar usando todos os recursos.');
        }

        return back()->with('status', 'Dados do perfil atualizados.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(config('formai.password_min_length'))],
        ]);

        $request->user()->forceFill(['password' => $data['password']])->save();

        return back()->with('status', 'Senha atualizada com segurança.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $oldPath = $user->avatar_path;
        $path = $request->file('avatar')->store('', 'avatars');
        $user->forceFill(['avatar_path' => $path])->save();

        if ($oldPath) {
            Storage::disk('avatars')->delete($oldPath);
        }

        return back()->with('status', 'Foto de perfil atualizada.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('avatars')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return back()->with('status', 'Foto de perfil removida.');
    }
}
