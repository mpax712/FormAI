@extends('layouts.app')

@section('title', isset($question) ? 'Editar questão · FormAI' : 'Nova questão · FormAI')

@section('content')
@php
    $isEditing = isset($question);
    $isEssay = old('type', $isEditing ? $question->type->value : 'essay') === 'essay';
    $oldOptions = old('options');
    $options = is_array($oldOptions)
        ? $oldOptions
        : ($isEditing ? $question->options->all() : []);
    $oldRubric = old('rubric');
    $rubric = is_array($oldRubric)
        ? $oldRubric
        : ($isEditing ? $question->rubricCriteria->all() : []);
@endphp

<div class="card p-4 p-lg-5">
    <div class="mb-4">
        <span class="eyebrow">Banco de questões</span>
        <h1 class="mt-2">{{ $isEditing ? 'Editar questão' : 'Nova questão' }}</h1>
        <p class="text-secondary mb-0">Defina o enunciado, a pontuação e os critérios usados na correção.</p>
    </div>

    <form method="post" data-bank-editor action="{{ $isEditing ? route('teacher.questions.update', $question) : route('teacher.questions.store') }}">
        @csrf
        @if($isEditing)
            @method('PUT')
        @endif

        <div class="question-type-step mb-4">
            <label class="form-label" for="type">1. Escolha o tipo de questão</label>
            <select class="form-select" id="type" name="type" required>
                <option value="" @selected(! $isEditing && ! old('type'))>Selecione para começar</option>
                <option value="essay" @selected(($isEditing || old('type')) && $isEssay)>Dissertativa — correção com IA e revisão</option>
                <option value="single_choice" @selected(($isEditing || old('type')) && ! $isEssay)>Escolha única — correção pelo gabarito</option>
            </select>
        </div>
        <fieldset data-bank-content @if(! $isEditing && ! old('type')) hidden disabled @endif>
        <h2 class="h5">2. Conteúdo e pontuação</h2>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="body">Enunciado</label>
                <textarea class="form-control" id="body" name="body" rows="5" maxlength="10000" required>{{ old('body', $question->body ?? '') }}</textarea>
            </div>
            <div class="col-md-4">
                <div>
                    <label class="form-label" for="max_score">Pontuação</label>
                    <input class="form-control" id="max_score" name="max_score" type="number" step="0.01" min="0.01" value="{{ old('max_score', $question->max_score ?? 1) }}" required>
                </div>
            </div>
        </div>

        <fieldset class="mt-3" data-bank-kind="essay">
            <label class="form-label" for="expected_answer">Resposta esperada</label>
            <textarea class="form-control" id="expected_answer" name="expected_answer" rows="3">{{ old('expected_answer', $question->expected_answer ?? '') }}</textarea>
            <div class="form-text">Obrigatória para questões dissertativas.</div>
            <label class="form-label mt-3" for="teacher_instruction">Como a IA deve corrigir</label>
            <textarea class="form-control" id="teacher_instruction" name="teacher_instruction" rows="3" maxlength="2000" placeholder="Ex.: aceite termos equivalentes e explique os pontos a melhorar.">{{ old('teacher_instruction', $question->teacher_instruction ?? '') }}</textarea>
        </fieldset>

        <fieldset class="my-4" data-bank-kind="single_choice" aria-labelledby="options-title">
            <h2 id="options-title" class="h5">Alternativas <small class="text-secondary">— para escolha única</small></h2>
            @foreach(range(0, max(3, count($options) - 1)) as $index)
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
        </fieldset>

        <fieldset class="my-4" data-bank-kind="essay" aria-labelledby="rubric-title">
            <h2 id="rubric-title" class="h5">Rubrica <small class="text-secondary">— para questão dissertativa</small></h2>
            @foreach(range(0, max(2, count($rubric) - 1)) as $index)
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
                            max="1000"
                            placeholder="Pontos"
                            value="{{ old("rubric.$index.weight", $isEditing ? optional($question->rubricCriteria->get($index))->weight : '') }}"
                        >
                    </div>
                </div>
            @endforeach
            <div class="form-text">Os pontos dos critérios devem somar exatamente a pontuação da questão.</div>
        </fieldset>

        <button class="btn btn-primary" type="submit">Salvar questão</button>
        </fieldset>
    </form>
</div>
@endsection
