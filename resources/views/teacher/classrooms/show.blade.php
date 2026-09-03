@extends('layouts.app')
@section('title', $classroom->name.' · FormAI')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1>{{ $classroom->name }}</h1><p class="text-secondary mb-0">{{ $classroom->description }}</p></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-primary" href="{{ route('teacher.classrooms.edit', $classroom) }}">Editar turma</a><a class="btn btn-primary" href="{{ route('teacher.activities.create', ['classroom_id' => $classroom->id]) }}">Nova atividade</a></div>
</div>

<div class="classroom-code-card mb-4">
    <div>
        <span class="classroom-code-label">Código de entrada dos alunos</span>
        <strong class="classroom-code-value" id="classroom-code">{{ substr($classroom->join_code, 0, 4) }}-{{ substr($classroom->join_code, 4) }}</strong>
        <p>Compartilhe este código com a turma. Cada solicitação aparecerá abaixo para sua aprovação.</p>
    </div>
    <button class="btn btn-light" type="button" data-copy-text="{{ $classroom->join_code }}" data-copy-feedback="Código copiado">Copiar código</button>
</div>

@if($classroom->pendingStudents->isNotEmpty())
<section class="card p-4 mb-4" aria-labelledby="pending-title">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4 mb-1" id="pending-title">Solicitações de entrada</h2><p class="text-secondary mb-0">Confira os dados antes de permitir o acesso à turma.</p></div><span class="badge text-bg-warning">{{ $classroom->pendingStudents->count() }} pendente(s)</span></div>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Aluno</th><th>E-mail</th><th>Solicitado em</th><th class="text-end">Decisão</th></tr></thead><tbody>
        @foreach($classroom->pendingStudents as $student)
            <tr><td><div class="student-cell">@if($student->avatarUrl())<img src="{{ $student->avatarUrl() }}" alt="">@else<span aria-hidden="true">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>@endif<strong>{{ $student->name }}</strong></div></td><td>{{ $student->email }}</td><td>{{ $student->pivot->created_at?->format('d/m/Y H:i') }}</td><td><div class="d-flex justify-content-end gap-2"><form method="post" action="{{ route('teacher.classrooms.requests.approve', [$classroom, $student]) }}">@csrf @method('PATCH')<button class="btn btn-success btn-sm" type="submit">Aprovar</button></form><form method="post" action="{{ route('teacher.classrooms.requests.reject', [$classroom, $student]) }}" data-confirm="Recusar a entrada deste aluno?">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm" type="submit">Recusar</button></form></div></td></tr>
        @endforeach
    </tbody></table></div>
</section>
@endif

<div class="row g-4">
    <div class="col-lg-7"><div class="card p-4 h-100"><h2 class="h4">Alunos aprovados</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nome</th><th>E-mail</th></tr></thead><tbody>@forelse($classroom->students as $student)<tr><td><div class="student-cell">@if($student->avatarUrl())<img src="{{ $student->avatarUrl() }}" alt="">@else<span aria-hidden="true">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>@endif<strong>{{ $student->name }}</strong></div></td><td>{{ $student->email }}</td></tr>@empty<tr><td colspan="2">Ainda não há alunos aprovados.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="col-lg-5">
        <div class="card p-4 mb-4"><h2 class="h4">Convidar por e-mail</h2><p class="text-secondary small">Você ainda pode enviar um convite direto quando preferir.</p><form method="post" action="{{ route('teacher.classrooms.invite', $classroom) }}">@csrf<label class="form-label" for="invite-email">E-mail</label><input class="form-control mb-3" id="invite-email" name="email" type="email" required><button class="btn btn-outline-primary" type="submit">Enviar convite</button></form></div>
        <div class="card p-4"><h2 class="h4">Atividades</h2><ul class="list-group list-group-flush">@forelse($classroom->activities as $activity)<li class="list-group-item px-0 d-flex justify-content-between"><a href="{{ route('teacher.activities.show', $activity) }}">{{ $activity->title }}</a><span class="badge text-bg-secondary">{{ $activity->status->value }}</span></li>@empty<li class="list-group-item px-0">Nenhuma atividade.</li>@endforelse</ul></div>
    </div>
</div>
@endsection
