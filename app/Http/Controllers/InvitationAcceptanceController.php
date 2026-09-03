<?php

namespace App\Http\Controllers;

use App\Domain\Classrooms\Models\Invitation;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InvitationAcceptanceController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->validInvitation($token);
        return view('invitations.accept', compact('invitation', 'token'));
    }
    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->validInvitation($token);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'password' => ['required', 'confirmed', Password::min(config('formai.password_min_length'))]]);
        $user = DB::transaction(function () use ($invitation, $data, $request) {
            $user = User::query()->where('email', $invitation->email)->first();
            if ($user && (! $request->user() || $request->user()->id !== $user->id)) {
                abort(422, 'Ja existe uma conta com este e-mail. Entre nela antes de aceitar o convite.');
            }
            $user ??= User::query()->create(['email' => $invitation->email, 'name' => $data['name'], 'password' => $data['password'], 'role' => UserRole::Student, 'is_active' => true]);
            abort_unless($user->isStudent(), 422, 'Este e-mail ja possui outro papel no sistema.');
            $invitation->classroom->members()->syncWithoutDetaching([$user->id => [
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $invitation->invited_by,
            ]]);
            $invitation->update(['accepted_at' => now()]);
            return $user;
        });
        if (! $user->hasVerifiedEmail()) { event(new Registered($user)); }
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('dashboard')->with('status', 'Voce entrou na turma.');
    }
    private function validInvitation(string $token): Invitation
    {
        return Invitation::query()->with('classroom')->where('token_hash', hash('sha256', $token))->whereNull('accepted_at')->where('expires_at', '>', now())->firstOrFail();
    }
}
