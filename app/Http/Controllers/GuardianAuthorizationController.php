<?php

namespace App\Http\Controllers;

use App\Application\Services\GuardianAuthorizationService;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GuardianAuthorizationController extends Controller
{
    public function show(string $token): View
    {
        $authorization = GuardianAuthorization::query()->with('user')
            ->where('token_hash', hash('sha256', $token))->first();

        return view('guardian.authorization', compact('authorization', 'token'));
    }

    public function decide(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,decline'],
            'guardian_name' => ['required_if:decision,approve', 'nullable', 'string', 'max:120'],
            'relationship' => ['accepted_if:decision,approve'],
            'terms' => ['accepted_if:decision,approve'],
        ]);

        $result = DB::transaction(function () use ($token, $data): string {
            $authorization = GuardianAuthorization::query()
                ->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $authorization || $authorization->accepted_at || $authorization->declined_at || $authorization->expires_at->isPast() || $authorization->terms_version !== config('legal.terms_version')) {
                return 'invalid';
            }
            if ($data['decision'] === 'decline') {
                $authorization->update(['declined_at' => now()]);
                return 'declined';
            }
            $authorization->update(['guardian_name' => trim($data['guardian_name']), 'accepted_at' => now()]);
            $authorization->user()->update(['guardian_approved_at' => now(), 'is_active' => true]);
            return 'approved';
        });

        if ($result === 'approved') {
            $user = GuardianAuthorization::query()->with('user')->where('token_hash', hash('sha256', $token))->first()?->user;
            if ($user && ! $user->hasVerifiedEmail()) event(new Registered($user));
        }

        return redirect()->route('guardian.result', $result);
    }

    public function result(string $status): View
    {
        abort_unless(in_array($status, ['approved', 'declined', 'invalid'], true), 404);
        return view('guardian.result', compact('status'));
    }

    public function resend(Request $request, GuardianAuthorizationService $service): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $user = User::query()->where('email', $data['email'])->where('age_band', 'under_13')->where('is_active', false)->first();
        if ($user && $user->guardianAuthorization && ! $user->guardianAuthorization->declined_at) {
            $service->request($user, $user->guardianAuthorization->guardian_email);
        }
        return back()->with('status', 'Se houver uma autorização pendente, enviaremos um novo link ao responsável.');
    }
}
