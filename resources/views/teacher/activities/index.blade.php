@extends('layouts.app')
@section('title', 'Atividades · FormAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1>Atividades</h1><p class="text-secondary mb-0">Rascunhos, entregas e resultados.</p></div><a class="btn btn-primary" href="{{ route('teacher.activities.create') }}">Nova atividade</a></div>
<div class="card p-3"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Titulo</th><th>Turma</th><th>Prazo</th><th>Estado</th><th>Entregas</th></tr></thead><tbody>@forelse($activities as $activity)<tr><td><a href="{{ route('teacher.activities.show',$activity) }}">{{ $activity->title }}</a></td><td>{{ $activity->classroom->name }}</td><td>{{ $activity->deadline_at?->format('d/m/Y H:i') ?? 'Sem prazo' }}</td><td><span class="badge text-bg-secondary">{{ $activity->status->value }}</span></td><td>{{ $activity->submissions_count }}</td></tr>@empty<tr><td colspan="5">Nenhuma atividade.</td></tr>@endforelse</tbody></table></div></div><div class="mt-4">{{ $activities->links() }}</div>
@endsection
