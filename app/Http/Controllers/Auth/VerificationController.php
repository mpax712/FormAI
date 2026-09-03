<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function notice(): View { return view('auth.verify-email'); }
    public function verify(EmailVerificationRequest $request): RedirectResponse { $request->fulfill(); return redirect()->route('dashboard')->with('status', 'E-mail verificado.'); }
    public function resend(Request $request): RedirectResponse { $request->user()->sendEmailVerificationNotification(); return back()->with('status', 'Link reenviado.'); }
}
