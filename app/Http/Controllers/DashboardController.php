<?php

namespace App\Http\Controllers;

use App\Application\Services\DashboardFilters;
use App\Application\Services\TeacherStatistics;
use App\Domain\Activities\Models\Activity;
use App\Domain\Classrooms\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if ($user->isAdmin()) return redirect()->route('admin.dashboard');
        if ($user->isTeacher()) {
            $filters = app(DashboardFilters::class)->resolve($request);
            $statistics = app(TeacherStatistics::class)->get($user->id, $filters);
            $classrooms = Classroom::where('teacher_id', $user->id)->orderBy('name')->get();
            $activities = new LengthAwarePaginator($statistics['activities'], $statistics['pagination']['total'], 10, $filters['page'], ['path' => $request->url(), 'query' => $request->query()]);
            $upcoming = Activity::where('teacher_id', $user->id)->where('status', '!=', 'draft')->whereBetween('deadline_at', [now(), now()->addDays(7)])->with('classroom')->orderBy('deadline_at')->limit(10)->get();
            return view('dashboard', compact('statistics', 'filters', 'classrooms', 'activities', 'upcoming'));
        }
        $available = Activity::whereHas('classroom.students', fn ($q) => $q->whereKey($user->id))
            ->where('status', '!=', 'draft')->with('classroom')->with(['submissions' => fn ($q) => $q->where('student_id', $user->id)])->orderBy('deadline_at')->get();
        $pending = $available->filter(fn ($a) => ! $a->submissions->first() || $a->submissions->first()->status->value === 'draft');
        [$open, $overdue] = $pending->partition(fn ($a) => $a->deadline_at === null || $a->deadline_at->isFuture() || ($a->submissions->first()?->reopened_until?->isFuture() ?? false));
        $results = $available->filter(fn ($a) => $a->submissions->first()?->status->value === 'released')->sortByDesc(fn ($a) => $a->submissions->first()->released_at)->take(10);
        $open = $open->sortBy(fn ($a) => ($a->submissions->first()?->reopened_until?->isFuture() ? $a->submissions->first()->reopened_until : $a->deadline_at)?->timestamp ?? PHP_INT_MAX);
        return view('student.dashboard', compact('open', 'overdue', 'results'));
    }

    public function statistics(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()->isTeacher(), 403);
        $request->validate(['summary' => ['sometimes', \Illuminate\Validation\Rule::in(['1'])]]);
        $filters = app(DashboardFilters::class)->resolve($request);
        if ($request->query('summary') === '1') {
            return response()->json(['summary' => app(TeacherStatistics::class)->summary($request->user()->id, $filters)]);
        }
        return response()->json(app(TeacherStatistics::class)->get($request->user()->id, $filters));
    }
}
