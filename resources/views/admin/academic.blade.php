@extends('layouts.app')
@section('title', 'Gestao academica · FormAI')
@section('content')
<h1>Gestao academica</h1><p class="alert alert-warning">O administrador possui acesso amplo. Toda visita e alteracao nesta area e auditada.</p>
<div class="card p-4 mb-4"><h2 class="h4">Turmas</h2><div class="table-responsive"><table class="table"><thead><tr><th>Turma</th><th>Professor</th><th></th></tr></thead><tbody>@foreach($classrooms as $classroom)<tr><td>{{ $classroom->name }}</td><td>{{ $classroom->teacher->name }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('teacher.classrooms.show',$classroom) }}">Administrar</a></td></tr>@endforeach</tbody></table></div>{{ $classrooms->links() }}</div>
<div class="card p-4"><h2 class="h4">Atividades recentes</h2><div class="table-responsive"><table class="table"><thead><tr><th>Atividade</th><th>Turma</th><th>Professor</th><th></th></tr></thead><tbody>@foreach($activities as $activity)<tr><td>{{ $activity->title }}</td><td>{{ $activity->classroom->name }}</td><td>{{ $activity->teacher->name }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('teacher.activities.show',$activity) }}">Abrir</a></td></tr>@endforeach</tbody></table></div></div>
@endsection
