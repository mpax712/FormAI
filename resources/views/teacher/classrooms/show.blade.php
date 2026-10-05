@extends('layouts.app')
@section('title', $classroom->name.' · FormAI')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1>{{ $classroom->name }}</h1><p class="text-secondary mb-0">{{ $classroom->description }}</p></div>
    <div class="d-flex gap-2 classroom-header-actions"><a class="btn btn-outline-primary" href="{{ route('teacher.classrooms.edit', $classroom) }}">Editar turma</a><a class="btn btn-primary" href="{{ route('teacher.activities.create', ['classroom_id' => $classroom->id]) }}">Nova atividade</a></div>
</div>

<section class="classroom-entry-card mb-4" aria-label="Entrada na turma">
    <div class="classroom-code-card">
        <div>
            <span class="classroom-code-label">Código de entrada dos alunos</span>
            <strong class="classroom-code-value" id="classroom-code">{{ substr($classroom->join_code, 0, 4) }}-<wbr>{{ substr($classroom->join_code, 4) }}</strong>
            <p>Compartilhe este código com seus alunos para que possam entrar na turma.</p>
        </div>
        <button class="btn btn-light" type="button" data-copy-text="{{ $classroom->join_code }}" data-copy-feedback="Código copiado">Copiar código</button>
    </div>
    <form class="classroom-auto-entry" method="post" action="{{ route('teacher.classrooms.auto-approve-join', $classroom) }}" data-auto-submit-switch>
        @csrf @method('PATCH')
        <div class="classroom-auto-entry-heading">
            <span class="classroom-auto-entry-eyebrow">Configuração de entrada</span>
            <span class="classroom-auto-entry-status {{ $classroom->auto_approve_join ? 'is-on' : '' }}">{{ $classroom->auto_approve_join ? 'Ativada' : 'Desativada' }}</span>
        </div>
        <div class="classroom-auto-entry-control">
            <label for="auto-approve-join">Autorizar entrada automaticamente</label>
            <input type="hidden" name="auto_approve_join" value="0">
            <input class="classroom-entry-switch" id="auto-approve-join" name="auto_approve_join" type="checkbox" role="switch" value="1" aria-describedby="auto-approve-join-help" @checked($classroom->auto_approve_join)>
        </div>
        <p id="auto-approve-join-help">{{ $classroom->auto_approve_join ? 'Novos alunos entram pelo código sem sua aprovação.' : 'Novos alunos que usarem o código aguardam sua aprovação.' }} Pedidos já pendentes continuam aguardando. Menores de 13 anos ainda precisam da autorização do responsável.</p>
        <button class="btn btn-outline-primary btn-sm" type="submit">Salvar alteração</button>
    </form>
</section>
<x-context-help title="Como alunos entram nesta turma?">Compartilhe o código com os alunos. Com a entrada automática desligada, aprove ou recuse novas solicitações abaixo. Ao ligá-la, apenas os novos cadastros por código serão aprovados automaticamente; solicitações anteriores permanecem pendentes. Convites por e-mail são outra forma de entrada. Contas de menores de 13 anos só ficam acessíveis após a autorização do responsável.</x-context-help>

@if($classroom->pendingStudents->isNotEmpty())
<section class="card p-4 mb-4" aria-labelledby="pending-title">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4 mb-1" id="pending-title">Solicitações de entrada</h2><p class="text-secondary mb-0">Confira os dados antes de permitir o acesso à turma.</p></div><span class="badge text-bg-warning">{{ $classroom->pendingStudents->count() }} pendente(s)</span></div>
    <div class="table-responsive mobile-card-table"><table class="table align-middle"><thead><tr><th>Aluno</th><th>E-mail</th><th>Solicitado em</th><th class="text-end">Decisão</th></tr></thead><tbody>
        @foreach($classroom->pendingStudents as $student)
            <tr><td><span class="mobile-cell-label d-md-none">Aluno</span><div class="student-cell">@if($student->avatarUrl())<img src="{{ $student->avatarUrl() }}" alt="">@else<span aria-hidden="true">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>@endif<strong>{{ $student->name }}</strong></div></td><td><span class="mobile-cell-label d-md-none">E-mail</span>@foreach(preg_split('/(?<=[@.])/', $student->email) as $part){{ $part }}<wbr>@endforeach</td><td><span class="mobile-cell-label d-md-none">Solicitado</span>{{ $student->pivot->created_at?->format('d/m/Y H:i') }}</td><td><span class="mobile-cell-label d-md-none">Decisão</span><div class="d-flex justify-content-end gap-2 mobile-actions"><form method="post" action="{{ route('teacher.classrooms.requests.approve', [$classroom, $student]) }}">@csrf @method('PATCH')<button class="btn btn-success btn-sm" type="submit">Aprovar</button></form><form method="post" action="{{ route('teacher.classrooms.requests.reject', [$classroom, $student]) }}" data-confirm="Recusar a entrada deste aluno?">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm" type="submit">Recusar</button></form></div></td></tr>
        @endforeach
    </tbody></table></div>
</section>
@endif

<div class="row g-4">
    <div class="col-lg-7"><div class="card p-4 h-100"><h2 class="h4">Alunos aprovados</h2><div class="table-responsive mobile-card-table"><table class="table align-middle"><thead><tr><th>Nome</th><th>E-mail</th></tr></thead><tbody>@forelse($classroom->students as $student)<tr><td><span class="mobile-cell-label d-md-none">Nome</span><div class="student-cell">@if($student->avatarUrl())<img src="{{ $student->avatarUrl() }}" alt="">@else<span aria-hidden="true">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>@endif<strong>{{ $student->name }}</strong></div></td><td><span class="mobile-cell-label d-md-none">E-mail</span>@foreach(preg_split('/(?<=[@.])/', $student->email) as $part){{ $part }}<wbr>@endforeach</td></tr>@empty<tr><td colspan="2">Ainda não há alunos aprovados.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="col-lg-5">
        <div class="card p-4 mb-4"><h2 class="h4">Convidar por e-mail</h2><p class="text-secondary small">Você ainda pode enviar um convite direto quando preferir.</p><form method="post" action="{{ route('teacher.classrooms.invite', $classroom) }}">@csrf<label class="form-label" for="invite-email">E-mail</label><input class="form-control mb-3" id="invite-email" name="email" type="email" required><button class="btn btn-outline-primary" type="submit">Enviar convite</button></form></div>
        <div class="card p-4"><h2 class="h4">Atividades</h2><ul class="list-group list-group-flush">@forelse($classroom->activities as $activity)<li class="list-group-item px-0 d-flex justify-content-between"><a href="{{ route('teacher.activities.show', $activity) }}">{{ $activity->title }}</a><x-workflow-status :value="$activity->status->value" /></li>@empty<li class="list-group-item px-0">Nenhuma atividade.</li>@endforelse</ul></div>
    </div>
</div>
@endsection
