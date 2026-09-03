@extends('layouts.app')
@section('title', 'Dashboard · FormAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1>Ola, {{ auth()->user()->name }}</h1><p class="text-secondary mb-0">Visao geral do seu espaco no FormAI.</p></div></div>
<div class="row g-4">
@foreach([['Turmas',$data['classrooms']],['Atividades',$data['activities']],['Pendencias',$data['pending']]] as [$label,$value])
<div class="col-md-4"><div class="card p-4 h-100"><div class="metric">{{ $value }}</div><div class="text-secondary">{{ $label }}</div></div></div>
@endforeach
</div>
<div class="card p-4 mt-4"><h2 class="h4">Proxima acao</h2>
@if(auth()->user()->isTeacher())<p>Crie uma turma e monte a atividade com perguntas novas ou itens opcionais do seu banco.</p><div><a class="btn btn-primary" href="{{ route('teacher.activities.create') }}">Nova atividade</a> <a class="btn btn-outline-primary" href="{{ route('teacher.classrooms.create') }}">Nova turma</a></div>
@elseif(auth()->user()->isStudent())<p>Consulte as atividades disponiveis e acompanhe resultados publicados.</p><div><a class="btn btn-primary" href="{{ route('student.activities.index') }}">Ver atividades</a></div>
@else<p>Monitore usuarios, jobs e acessos administrativos.</p><div><a class="btn btn-primary" href="{{ route('admin.dashboard') }}">Abrir administracao</a></div>@endif
</div>
@if(auth()->user()->isStudent() && auth()->user()->pendingClassrooms()->exists())<div class="alert alert-warning mt-4"><strong>Entrada aguardando aprovação.</strong> Seu professor precisa aprovar sua solicitação antes que as atividades da turma apareçam.</div>@endif
@endsection
