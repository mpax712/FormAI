@extends('layouts.app')
@section('title', isset($classroom) ? 'Editar turma' : 'Nova turma')
@section('content')
<div class="row justify-content-center"><div class="col-lg-8"><div class="card p-4"><h1>{{ isset($classroom) ? 'Editar turma' : 'Nova turma' }}</h1><form method="post" action="{{ isset($classroom) ? route('teacher.classrooms.update', $classroom) : route('teacher.classrooms.store') }}">@csrf @isset($classroom) @method('PUT') @endisset
<div class="mb-3"><label class="form-label" for="name">Nome</label><input class="form-control" id="name" name="name" value="{{ old('name', $classroom->name ?? '') }}" maxlength="150" required></div>
<div class="mb-3"><label class="form-label" for="description">Descricao</label><textarea class="form-control" id="description" name="description" rows="4" maxlength="3000">{{ old('description', $classroom->description ?? '') }}</textarea></div>
@isset($classroom)<div class="form-check mb-3"><input type="hidden" name="is_active" value="0"><input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $classroom->is_active))><label class="form-check-label" for="is_active">Turma ativa</label></div>@endisset
<button class="btn btn-primary" type="submit">Salvar</button></form></div></div></div>
@endsection
