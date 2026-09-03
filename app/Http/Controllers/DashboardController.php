<?php

namespace App\Http\Controllers;

use App\Domain\Activities\Models\Activity;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Submissions\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        $data = Cache::remember('dashboard:'.$user->role->value.':'.$user->id, 60, fn () => $user->isTeacher() ? [
                'classrooms' => Classroom::query()->where('teacher_id', $user->id)->count(),
                'activities' => Activity::query()->where('teacher_id', $user->id)->count(),
                'pending' => Submission::query()->whereHas('activity', fn ($q) => $q->where('teacher_id', $user->id))->whereIn('status', ['submitted', 'processing'])->count(),
            ] : [
                'classrooms' => $user->classrooms()->count(),
                'activities' => Activity::query()->whereHas('classroom.students', fn ($q) => $q->whereKey($user->id))->whereIn('status', ['published', 'released'])->count(),
                'pending' => $user->submissions()->where('status', 'draft')->count(),
            ]);
        return view('dashboard', compact('data'));
    }
}
