<?php

namespace App\Http\Controllers\Teacher;

use App\Domain\Classrooms\Models\Classroom;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Classroom::class);
        $classrooms = Classroom::query()->where('teacher_id', $request->user()->id)->withCount(['students', 'activities'])->latest()->paginate(15);
        return view('teacher.classrooms.index', compact('classrooms'));
    }
    public function create(): View { $this->authorize('create', Classroom::class); return view('teacher.classrooms.form'); }
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Classroom::class);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:3000']]);
        $classroom = $request->user()->classroomsOwned()->create($data + ['is_active' => true]);
        Cache::forget('dashboard:teacher:'.$request->user()->id);
        return redirect()->route('teacher.classrooms.show', $classroom)->with('status', 'Turma criada.');
    }
    public function show(Classroom $classroom): View
    {
        $this->authorize('view', $classroom);
        $classroom->load([
            'students:id,public_id,name,email,avatar_path',
            'pendingStudents:id,public_id,name,email,avatar_path',
            'activities' => fn ($q) => $q->latest(),
        ]);
        return view('teacher.classrooms.show', compact('classroom'));
    }
    public function edit(Classroom $classroom): View { $this->authorize('update', $classroom); return view('teacher.classrooms.form', compact('classroom')); }
    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorize('update', $classroom);
        $classroom->update($request->validate(['name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:3000'], 'is_active' => ['sometimes', 'boolean']]));
        return redirect()->route('teacher.classrooms.show', $classroom)->with('status', 'Turma atualizada.');
    }
}
