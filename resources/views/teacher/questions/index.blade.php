@extends('layouts.app')
@section('title', 'Banco de questões · FormAI')
@section('content')
<div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-4"><div><span class="eyebrow">Biblioteca do professor</span><h1>Banco de questões</h1><p class="text-secondary mb-0">Organize perguntas, gabaritos e critérios para reutilizar nas atividades.</p></div><a class="btn btn-primary" href="{{ route('teacher.questions.create') }}">Nova questão</a></div>
<form method="get" class="d-flex gap-2 mb-4"><input class="form-control" type="search" name="q" maxlength="120" value="{{ request('q') }}" aria-label="Buscar questões" placeholder="Buscar pelo enunciado"><button class="btn btn-outline-primary">Buscar</button></form>
<div class="row g-3">
@forelse($questions as $question)
    <div class="col-md-6 col-xl-4"><article class="card h-100 p-4">
        <div class="d-flex justify-content-between gap-2 mb-3"><span class="badge text-bg-light">{{ $question->type->value === 'essay' ? 'Dissertativa' : 'Escolha única' }}</span><strong>{{ number_format((float) $question->max_score, 2, ',', '.') }} pts</strong></div>
        <h2 class="h5 text-break">{{ Str::limit($question->body, 220) }}</h2>
        <p class="text-secondary small">{{ $question->type->value === 'essay' ? $question->rubric_criteria_count.' critérios de correção' : $question->options_count.' alternativas' }}</p>
        <div class="d-flex gap-2 flex-wrap mt-auto">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('teacher.questions.edit', $question) }}">Editar</a>
            <a class="btn btn-sm btn-primary" href="{{ route('teacher.activities.create', ['bank_selection' => 1, 'bank_questions' => [$question->id]]) }}">Usar em atividade</a>
            <form method="post" action="{{ route('teacher.questions.destroy', $question) }}" data-confirm="Arquivar esta questão?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Arquivar</button></form>
        </div>
    </article></div>
@empty
    <div class="col-12"><div class="card p-5 text-center"><h2 class="h4">Nenhuma questão encontrada</h2><p class="text-secondary mb-0">Crie sua primeira questão ou ajuste a busca.</p></div></div>
@endforelse
</div>
<div class="mt-4">{{ $questions->links() }}</div>
@endsection
