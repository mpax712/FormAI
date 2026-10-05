@extends('layouts.app')
@section('title', 'Atividades · FormAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1>Atividades</h1><p class="text-secondary mb-0">Rascunhos, entregas e resultados.</p></div>
    <a class="btn btn-primary" data-tour="new-activity" href="{{ route('teacher.activities.create') }}">Nova atividade</a>
</div>
<x-context-help title="Como ler esta lista?">Nova atividade inicia um rascunho. Entregues mostra quantos alunos já enviaram; Pendentes mostra quantos ainda não enviaram entre os alunos aprovados da turma. Abra o título para acompanhar entregas e correções; Editar altera a atividade; Excluir remove também as respostas e notas relacionadas.</x-context-help>

<form class="card p-3 mb-4" method="get" action="{{ route('teacher.activities.index') }}" role="search">
    <div class="row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label" for="activity-search">Buscar por nome</label><input class="form-control" id="activity-search" type="search" name="q" maxlength="180" value="{{ $search }}" placeholder="Digite o título da atividade"></div>
        <div class="col-md-4"><label class="form-label" for="activity-classroom">Filtrar por turma</label><select class="form-select" id="activity-classroom" name="classroom_id"><option value="">Todas as turmas</option>@foreach($classrooms as $classroom)<option value="{{ $classroom->id }}" @selected((string) request('classroom_id') === (string) $classroom->id)>{{ $classroom->name }}{{ $classroom->is_active ? '' : ' (inativa)' }}</option>@endforeach</select></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary flex-grow-1" type="submit">Filtrar</button>@if($search !== '' || request('classroom_id'))<a class="btn btn-outline-secondary" href="{{ route('teacher.activities.index') }}">Limpar</a>@endif</div>
    </div>
</form>

<div class="card p-3" data-tour="activity-list"><div class="table-responsive mobile-card-table"><table class="table align-middle">
    <thead><tr><th>Título</th><th>Turma</th><th>Prazo</th><th>Estado</th><th>Entregues</th><th>Ainda não entregues</th><th class="text-end">Ações</th></tr></thead>
    <tbody>@forelse($activities as $activity)
        @php($missingCount = max(0, (int) $activity->classroom->students_count - (int) $activity->delivered_count))
        <tr><td><span class="mobile-cell-label d-xl-none">Título</span><a href="{{ route('teacher.activities.show', $activity) }}">{{ $activity->title }}</a></td><td><span class="mobile-cell-label d-xl-none">Turma</span>{{ $activity->classroom->name }}</td><td><span class="mobile-cell-label d-xl-none">Prazo</span>{{ $activity->deadline_at?->format('d/m/Y H:i') ?? 'Sem prazo' }}</td><td><span class="mobile-cell-label d-xl-none">Estado</span><x-workflow-status :value="$activity->status->value" /></td><td><span class="mobile-cell-label d-xl-none">Entregues</span>{{ $activity->delivered_count }}</td><td><span class="mobile-cell-label d-xl-none">Pendentes</span>{{ $activity->status === App\Domain\Activities\Enums\ActivityStatus::Draft ? '—' : $missingCount }}</td><td><span class="mobile-cell-label d-xl-none">Ações</span><div class="d-flex justify-content-end gap-2 mobile-actions"><a class="btn btn-sm btn-outline-primary" href="{{ route('teacher.activities.edit', $activity) }}">Editar</a><form method="post" action="{{ route('teacher.activities.destroy', $activity) }}" data-confirm="Excluir definitivamente esta atividade? Respostas, correções e notas relacionadas também serão apagadas.">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button></form></div></td></tr>
    @empty<tr><td colspan="7">Nenhuma atividade encontrada.</td></tr>@endforelse</tbody>
</table></div></div>
<div class="mt-4">{{ $activities->links() }}</div>
@endsection
