<?php

namespace App\Http\Controllers;

use App\Domain\Classrooms\Models\Invitation;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use App\Application\Services\GuardianAuthorizationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InvitationAcceptanceController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->validInvitation($token);
        return view('invitations.accept', compact('invitation', 'token'));
    }
    public function store(Request $request, string $token, GuardianAuthorizationService $guardianService): RedirectResponse
    {
        $invitation = $this->validInvitation($token);
        $request->merge(['guardian_email' => Str::lower(trim((string) $request->input('guardian_email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(config('formai.password_min_length'))],
            'age_band' => ['required', 'in:under_13,13_17,adult'],
            'guardian_email' => ['required_if:age_band,under_13', 'nullable', 'email:rfc,dns', 'max:255', Rule::notIn([$invitation->email])],
            'terms' => ['accepted'],
        ]);
        $needsGuardian = $data['age_band'] === 'under_13';
        $user = DB::transaction(function () use ($invitation, $data, $request) {
            $user = User::query()->where('email', $invitation->email)->first();
            if ($user && (! $request->user() || $request->user()->id !== $user->id)) {
                abort(422, 'Ja existe uma conta com este e-mail. Entre nela antes de aceitar o convite.');
            }
            if ($user && ($data['age_band'] === 'under_13' || $user->age_band === 'under_13') && ! $user->guardian_approved_at) {
                abort(422, 'Esta conta precisa de autorização do responsável antes de aceitar o convite.');
            }
            $user ??= User::query()->create([
                'email' => $invitation->email,
                'name' => $data['name'],
                'password' => $data['password'],
                'role' => UserRole::Student,
                'is_active' => $data['age_band'] !== 'under_13',
                'age_band' => $data['age_band'],
                'terms_version' => config('legal.terms_version'),
                'terms_accepted_at' => now(),
            ]);
            abort_unless($user->isStudent(), 422, 'Este e-mail ja possui outro papel no sistema.');
            if ($user->terms_version !== config('legal.terms_version')) {
                $user->forceFill(['terms_version' => config('legal.terms_version'), 'terms_accepted_at' => now()])->save();
            }
            $invitation->classroom->members()->syncWithoutDetaching([$user->id => [
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $invitation->invited_by,
            ]]);
            $invitation->update(['accepted_at' => now()]);
            return $user;
        });
        if ($needsGuardian && ! $user->is_active) {
            $guardianService->request($user, $data['guardian_email']);
            return redirect()->route('guardian.pending')->with('status', 'Convite registrado. O responsável precisa autorizar a conta antes que o aluno possa entrar.');
        }
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
