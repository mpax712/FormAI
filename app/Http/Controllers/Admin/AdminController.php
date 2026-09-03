<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Activities\Models\Activity;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Grading\Models\GradingRun;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.dashboard', ['metrics' => [
            'users' => User::query()->count(), 'classrooms' => Classroom::query()->count(), 'activities' => Activity::query()->count(),
            'failed_ai' => GradingRun::query()->whereIn('status', ['retryable_failed', 'permanently_failed'])->count(),
            'queued' => DB::table('jobs')->count(), 'failed_jobs' => DB::table('failed_jobs')->count(),
        ], 'audits' => AuditLog::query()->with('actor:id,name')->latest()->limit(20)->get()]);
    }
    public function users(Request $request): View
    {
        $users = User::query()->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($x) => $x->where('name', 'like', "%$term%")->orWhere('email', 'like', "%$term%")))->latest()->paginate(25);
        return view('admin.users', compact('users'));
    }
    public function academic(): View
    {
        return view('admin.academic', [
            'classrooms' => Classroom::query()->with('teacher:id,name')->latest()->paginate(15, ['*'], 'turmas'),
            'activities' => Activity::query()->with(['teacher:id,name', 'classroom:id,name'])->latest()->limit(30)->get(),
        ]);
    }
    public function updateUser(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id && ! $request->boolean('is_active'), 422, 'Voce nao pode desativar a propria conta.');
        $data = $request->validate(['role' => ['required', Rule::in(['admin', 'teacher', 'student'])], 'is_active' => ['required', 'boolean']]);
        abort_if($user->id === $request->user()->id && $data['role'] !== 'admin', 422, 'Voce nao pode remover o proprio papel administrativo.');
        abort_if($user->isAdmin() && $data['role'] !== 'admin' && User::query()->where('role', 'admin')->count() <= 1, 422, 'O sistema deve manter ao menos um administrador.');
        $user->update($data);
        return back()->with('status', 'Usuario atualizado e alteracao auditada.');
    }
}
