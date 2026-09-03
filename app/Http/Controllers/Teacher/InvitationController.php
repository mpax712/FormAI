<?php

namespace App\Http\Controllers\Teacher;

use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Classrooms\Notifications\ClassInvitationNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function store(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorize('update', $classroom);
        $data = $request->validate(['email' => ['required', 'email:rfc,dns', 'max:255']]);
        $token = Str::random(64);
        $classroom->invitations()->create(['email' => Str::lower($data['email']), 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'invited_by' => $request->user()->id]);
        Notification::route('mail', $data['email'])->notify(new ClassInvitationNotification($classroom, $token));
        return back()->with('status', 'Convite enviado.');
    }
}
