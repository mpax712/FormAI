@extends('layouts.app')
@section('title', 'Turmas · FormAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1>Turmas</h1><p class="text-secondary mb-0">Organize alunos e atividades.</p></div><a class="btn btn-primary" href="{{ route('teacher.classrooms.create') }}">Nova turma</a></div>
<div class="row g-3">@forelse($classrooms as $classroom)<div class="col-md-6"><div class="card p-4 h-100"><div class="d-flex justify-content-between"><h2 class="h4">{{ $classroom->name }}</h2><span class="badge {{ $classroom->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $classroom->is_active ? 'Ativa' : 'Inativa' }}</span></div><p class="text-secondary">{{ $classroom->description ?: 'Sem descricao.' }}</p><p>{{ $classroom->students_count }} alunos · {{ $classroom->activities_count }} atividades</p><a class="btn btn-outline-primary mt-auto" href="{{ route('teacher.classrooms.show', $classroom) }}">Abrir turma</a></div></div>@empty<div class="col"><div class="alert alert-info">Nenhuma turma criada.</div></div>@endforelse</div>
<div class="mt-4">{{ $classrooms->links() }}</div>
@endsection
