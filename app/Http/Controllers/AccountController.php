<?php

namespace App\Http\Controllers;

use App\Domain\Administration\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function destroy(Request $request): RedirectResponse
    {
        abort_if($request->user()->isAdmin(), 422, 'Administradores devem transferir o acesso antes de excluir a conta.');
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();
        AuditLog::query()->create(['actor_id' => $user->id, 'event' => 'account.deletion_requested', 'route' => $request->route()?->getName(), 'ip_address' => $request->ip(), 'correlation_id' => $request->attributes->get('correlation_id'), 'metadata' => []]);
        $user->forceFill(['is_active' => false, 'deleted_requested_at' => now(), 'remember_token' => null])->save();
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('home')->with('status', 'Conta desativada. Os dados pessoais serao anonimizados apos 30 dias.');
    }
}
