<?php

namespace App\Http\Controllers\Teacher;

use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MembershipRequestController extends Controller
{
    public function approve(Request $request, Classroom $classroom, User $student): RedirectResponse
    {
        $this->authorize('update', $classroom);
        abort_unless($student->isStudent() && $classroom->pendingStudents()->whereKey($student->id)->exists(), 404);

        $classroom->pendingStudents()->updateExistingPivot($student->id, [
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        Cache::forget('dashboard:student:'.$student->id);
        Cache::forget('dashboard:teacher:'.$request->user()->id);

        return back()->with('status', $student->name.' foi aprovado na turma.');
    }

    public function reject(Request $request, Classroom $classroom, User $student): RedirectResponse
    {
        $this->authorize('update', $classroom);
        abort_unless($student->isStudent() && $classroom->pendingStudents()->whereKey($student->id)->exists(), 404);

        $classroom->pendingStudents()->detach($student->id);
        Cache::forget('dashboard:student:'.$student->id);

        return back()->with('status', 'Solicitação recusada.');
    }
}
