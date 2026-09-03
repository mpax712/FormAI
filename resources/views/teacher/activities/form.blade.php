@extends('layouts.app')

@section('title', isset($activity) ? 'Editar atividade · FormAI' : 'Nova atividade · FormAI')
@section('body-class', 'app-page activity-editor-page')

@section('content')
@php
    $isEditing = isset($activity);
    $rowsInput = old('questions', $questionRows);
    $rows = is_array($rowsInput) ? $rowsInput : [];
    if (! $isEditing && old('questions') === null && count($rows) === 0) {
        $rows = [['type' => 'essay', 'max_score' => 1, 'rubric' => [['label' => 'Qualidade geral', 'description' => 'Clareza, correção e completude da resposta.', 'weight' => 1]]]];
    }
    $selectedBank = array_map('intval', $selectedBankQuestionIds);
@endphp

<div class="activity-form-header activity-editor-hero">
    <div><span class="eyebrow">Criação de atividade</span><h1>{{ $isEditing ? 'Editar atividade' : 'Nova atividade' }}</h1><p>Organize a proposta, escolha a turma e construa cada questão em um fluxo mais claro e direto.</p></div>
    <div class="activity-editor-status" aria-label="Estado da atividade"><span></span><div><small>Estado atual</small><strong>{{ $isEditing ? 'Rascunho em edição' : 'Nova atividade' }}</strong></div></div>
</div>

<form method="post" action="{{ $isEditing ? route('teacher.activities.update', $activity) : route('teacher.activities.store') }}" data-activity-builder>
    @csrf
    @if($isEditing) @method('PUT') @endif

    <section class="card activity-data-card p-4 mb-4" aria-labelledby="activity-data-title">
        <div class="activity-section-title"><span class="activity-section-icon" aria-hidden="true">01</span><div><h2 id="activity-data-title" class="h4 mb-1">Dados da atividade</h2><p>Defina as informações que serão apresentadas aos alunos.</p></div></div>
        <div class="row g-3">
            <div class="col-md-7"><label class="form-label" for="title">Título</label><input class="form-control" id="title" name="title" value="{{ old('title', $activity->title ?? '') }}" maxlength="180" required></div>
            <div class="col-md-5"><label class="form-label" for="classroom_id">Turma</label><select class="form-select" id="classroom_id" name="classroom_id" required><option value="">Selecione a turma</option>@foreach($classrooms as $classroom)<option value="{{ $classroom->id }}" @selected(old('classroom_id', $activity->classroom_id ?? request('classroom_id')) == $classroom->id) @disabled(! $classroom->is_active && (! $isEditing || $activity->classroom_id !== $classroom->id))>{{ $classroom->name }}{{ $classroom->is_active ? '' : ' (inativa — disponível apenas para salvar o rascunho)' }}</option>@endforeach</select></div>
            <div class="col-md-8"><label class="form-label" for="description">Instruções para os alunos</label><textarea class="form-control" id="description" name="description" rows="3" maxlength="5000">{{ old('description', $activity->description ?? '') }}</textarea></div>
            <div class="col-md-4"><label class="form-label" for="deadline_at">Prazo <span class="text-secondary fw-normal">(opcional)</span></label><input class="form-control" id="deadline_at" name="deadline_at" type="datetime-local" value="{{ old('deadline_at', $isEditing ? $activity->deadline_at?->format('Y-m-d\TH:i') : '') }}"><div class="form-text">Sem prazo, a atividade permanece aberta.</div></div>
        </div>
    </section>

    <section class="activity-questions-section" aria-labelledby="activity-questions-title">
        <div class="section-toolbar">
            <div><span class="eyebrow">Conteúdo da prova</span><h2 id="activity-questions-title">Perguntas da atividade</h2><p>Crie perguntas próprias abaixo ou reutilize itens do seu banco.</p></div>
        </div>

        <div class="question-add-toolbar glass-surface" role="toolbar" aria-label="Adicionar questão">
            <div class="question-add-toolbar-copy">
                <span class="question-add-symbol" aria-hidden="true">+</span>
                <div><strong>Adicionar nova questão</strong><small><span data-question-count>{{ count($rows) }}</span> <span data-question-count-label>{{ count($rows) === 1 ? 'questão criada' : 'questões criadas' }}</span></small></div>
            </div>
            <div class="question-add-actions">
                <button class="btn question-add-button question-add-essay" type="button" data-add-question="essay"><span aria-hidden="true">¶</span> Adicionar dissertativa</button>
                <button class="btn question-add-button question-add-choice" type="button" data-add-question="single_choice"><span aria-hidden="true">☷</span> Adicionar alternativa</button>
            </div>
        </div>

        <div data-question-list>
            @foreach($rows as $index => $question)
                @include('teacher.activities._question-fields', ['index' => $index, 'question' => $question])
            @endforeach
        </div>

        <div class="empty-question-state" data-question-empty @if(count($rows) > 0) hidden @endif>
            <strong>Comece pela primeira pergunta</strong><p>Você também pode selecionar uma pergunta pronta no banco abaixo.</p>
        </div>

        <details class="bank-picker glass-surface" @if(count($selectedBank) > 0 || request('bank_q')) open @endif>
            <summary><span><strong>Usar banco de questões</strong><small data-bank-count>Opcional · {{ $bankQuestions->total() }} para selecionar</small></span><span class="bank-picker-action">Selecionar</span></summary>
            <div class="bank-picker-body" data-bank-picker-body>
                <div class="input-group mb-3">
                    <input class="form-control" type="search" value="{{ request('bank_q') }}" placeholder="Buscar pelo enunciado" aria-label="Buscar no banco de questões" data-bank-search>
                    <button class="btn btn-outline-primary" type="button" data-bank-search-button>Buscar</button>
                </div>

                @if($selectedBankQuestions->isNotEmpty())
                    <div class="mb-3" data-selected-bank-questions>
                        <strong class="d-block mb-2">Perguntas selecionadas</strong>
                        @foreach($selectedBankQuestions as $bankQuestion)
                            <label class="bank-question-option">
                                <input class="form-check-input" type="checkbox" name="bank_questions[]" value="{{ $bankQuestion['id'] }}" checked>
                                <span><strong>{{ $bankQuestion['body'] }}</strong><small>{{ $bankQuestion['type']->value === 'essay' ? 'Dissertativa' : 'Escolha única' }} · {{ number_format((float) $bankQuestion['max_score'], 2, ',', '.') }} pontos @if(! $bankQuestion['available'])<span class="text-danger"> · Indisponível — remova ou substitua</span>@endif</small></span>
                            </label>
                        @endforeach
                    </div>
                @endif

                @forelse($bankQuestions as $bankQuestion)
                    <label class="bank-question-option">
                        <input class="form-check-input" type="checkbox" name="bank_questions[]" value="{{ $bankQuestion->id }}" @checked(in_array($bankQuestion->id, $selectedBank, true))>
                        <span><strong>{{ $bankQuestion->body }}</strong><small>{{ $bankQuestion->type->value === 'essay' ? 'Dissertativa' : 'Escolha única' }} · {{ number_format((float) $bankQuestion->max_score, 2, ',', '.') }} pontos</small></span>
                    </label>
                @empty
                    <p class="text-secondary mb-0">Nenhuma outra pergunta foi encontrada. Você pode criar perguntas diretamente nesta atividade.</p>
                @endforelse
                @if($bankQuestions->hasPages())
                    <div class="mt-3" data-bank-pagination>{{ $bankQuestions->links() }}</div>
                @endif
            </div>
        </details>
    </section>

    <div class="activity-form-actions glass-surface">
        <div><strong>{{ $isEditing ? 'Atualizar atividade' : 'Finalizar criação' }}</strong><small>O rascunho pode ser editado antes da publicação.</small></div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-quiet" type="submit" name="intent" value="draft">Salvar rascunho</button>
            <button class="btn btn-preview" type="submit" name="intent" value="preview"><span aria-hidden="true">◉</span> Visualizar</button>
            <button class="btn btn-success" type="submit" name="intent" value="publish" data-confirm-publish>Salvar e publicar</button>
        </div>
    </div>
</form>

<template id="activity-question-template-essay" data-question-template="essay">
    @include('teacher.activities._question-fields', ['index' => '__INDEX__', 'question' => ['type' => 'essay', 'max_score' => 1, 'rubric' => [['label' => 'Qualidade geral', 'description' => 'Clareza, correção e completude da resposta.', 'weight' => 1]]]])
</template>
<template id="activity-question-template-choice" data-question-template="single_choice">
    @include('teacher.activities._question-fields', ['index' => '__INDEX__', 'question' => ['type' => 'single_choice', 'max_score' => 1, 'correct_option' => 0, 'options' => [[], [], [], []]]])
</template>
@endsection
