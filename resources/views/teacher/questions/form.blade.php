@extends('layouts.app')

@section('title', isset($question) ? 'Editar questão · FormAI' : 'Nova questão · FormAI')

@section('content')
@php
    $isEditing = isset($question);
    $isEssay = old('type', $isEditing ? $question->type->value : 'essay') === 'essay';
@endphp

<div class="card p-4 p-lg-5">
    <div class="mb-4">
        <span class="eyebrow">Banco de questões</span>
        <h1 class="mt-2">{{ $isEditing ? 'Editar questão' : 'Nova questão' }}</h1>
        <p class="text-secondary mb-0">Defina o enunciado, a pontuação e os critérios usados na correção.</p>
    </div>

    <form method="post" action="{{ $isEditing ? route('teacher.questions.update', $question) : route('teacher.questions.store') }}">
        @csrf
        @if($isEditing)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="body">Enunciado</label>
                <textarea class="form-control" id="body" name="body" rows="5" maxlength="10000" required>{{ old('body', $question->body ?? '') }}</textarea>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label class="form-label" for="type">Tipo</label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="essay" @selected($isEssay)>Dissertativa</option>
                        <option value="single_choice" @selected(! $isEssay)>Escolha única</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="max_score">Pontuação</label>
                    <input class="form-control" id="max_score" name="max_score" type="number" step="0.01" min="0.01" value="{{ old('max_score', $question->max_score ?? 1) }}" required>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <label class="form-label" for="expected_answer">Resposta esperada</label>
            <textarea class="form-control" id="expected_answer" name="expected_answer" rows="3">{{ old('expected_answer', $question->expected_answer ?? '') }}</textarea>
            <div class="form-text">Obrigatória para questões dissertativas.</div>
        </div>

        <section class="mb-4" aria-labelledby="options-title">
            <h2 id="options-title" class="h5">Alternativas <small class="text-secondary">— para escolha única</small></h2>
            @foreach(range(0, 3) as $index)
                <div class="input-group mb-2">
                    <div class="input-group-text">
                        <input
                            class="form-check-input mt-0"
                            type="checkbox"
                            name="options[{{ $index }}][is_correct]"
                            value="1"
                            aria-label="Marcar alternativa {{ chr(65 + $index) }} como correta"
                            @checked(old("options.$index.is_correct", $isEditing ? optional($question->options->get($index))->is_correct : $index === 0))
                        >
                    </div>
                    <input
                        class="form-control"
                        name="options[{{ $index }}][text]"
                        placeholder="Alternativa {{ chr(65 + $index) }}"
                        value="{{ old("options.$index.text", $isEditing ? optional($question->options->get($index))->text : '') }}"
                    >
                </div>
            @endforeach
            <div class="form-text">Preencha ao menos duas opções e marque exatamente uma como correta.</div>
        </section>

        <section class="mb-4" aria-labelledby="rubric-title">
            <h2 id="rubric-title" class="h5">Rubrica <small class="text-secondary">— para questão dissertativa</small></h2>
            @foreach(range(0, 2) as $index)
                <div class="row g-2 mb-2">
                    <div class="col-md-3">
                        <input
                            class="form-control"
                            name="rubric[{{ $index }}][label]"
                            aria-label="Nome do critério {{ $index + 1 }}"
                            placeholder="Critério"
                            value="{{ old("rubric.$index.label", $isEditing ? optional($question->rubricCriteria->get($index))->label : '') }}"
                        >
                    </div>
                    <div class="col-md-7">
                        <input
                            class="form-control"
                            name="rubric[{{ $index }}][description]"
                            aria-label="Descrição do critério {{ $index + 1 }}"
                            placeholder="O que deve ser observado"
                            value="{{ old("rubric.$index.description", $isEditing ? optional($question->rubricCriteria->get($index))->description : '') }}"
                        >
                    </div>
                    <div class="col-md-2">
                        <input
                            class="form-control"
                            name="rubric[{{ $index }}][weight]"
                            aria-label="Peso do critério {{ $index + 1 }}"
                            type="number"
                            step="0.01"
                            min="0"
                            max="1"
                            placeholder="Peso"
                            value="{{ old("rubric.$index.weight", $isEditing ? optional($question->rubricCriteria->get($index))->weight : ($index === 0 ? 1 : '')) }}"
                        >
                    </div>
                </div>
            @endforeach
            <div class="form-text">Os pesos dos critérios preenchidos devem somar 1,00.</div>
        </section>

        <button class="btn btn-primary" type="submit">Salvar questão</button>
    </form>
</div>
@endsection
