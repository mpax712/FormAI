@extends('layouts.app')

@section('title', isset($activity) ? 'Editar atividade · FormAI' : 'Nova atividade · FormAI')
@section('body-class', 'app-page activity-editor-page')

@section('content')
@php
    $isEditing = isset($activity);
    $metadataOnly = $metadataOnly ?? false;
    $isDraft = ! $isEditing || $activity->status === App\Domain\Activities\Enums\ActivityStatus::Draft;
    $rowsInput = old('questions', $questionRows);
    $rows = is_array($rowsInput) ? $rowsInput : [];
    if (! $isEditing && old('questions') === null && count($rows) === 0) {
        $rows = [['type' => 'essay', 'max_score' => 1, 'rubric' => []]];
    }
    $selectedBank = array_map('intval', $selectedBankQuestionIds);
@endphp

<div class="activity-form-header activity-editor-hero">
    <div><span class="eyebrow">{{ $isEditing ? 'Edição de atividade' : 'Criação de atividade' }}</span><h1>{{ $isEditing ? 'Editar atividade' : 'Nova atividade' }}</h1><p>{{ $metadataOnly ? 'Atualize os dados gerais sem alterar as perguntas ou as entregas existentes.' : 'Organize a proposta, escolha a turma e construa cada questão em um fluxo mais claro e direto.' }}</p></div>
    <div class="activity-editor-status" aria-label="Estado da atividade"><span></span><div><small>Estado atual</small><strong>{{ $isEditing ? ($metadataOnly ? 'Edição de dados gerais' : ($isDraft ? 'Rascunho em edição' : 'Atividade publicada')) : 'Nova atividade' }}</strong></div></div>
</div>

<nav class="activity-editor-steps" aria-label="Etapas da atividade">
    <a href="#activity-data-title"><span>01</span> Dados</a>
    <a href="#activity-settings"><span>02</span> Correção</a>
    @if(! $metadataOnly)<a href="#activity-questions-title"><span>03</span> Questões</a>@endif
    <a href="#activity-actions"><span>{{ $metadataOnly ? '03' : '04' }}</span> Finalizar</a>
</nav>

<form method="post" action="{{ $isEditing ? route('teacher.activities.update', $activity) : route('teacher.activities.store') }}" @if(! $metadataOnly) data-activity-builder @endif>
    @csrf
    @if($isEditing) @method('PUT') @endif

    <section class="card activity-data-card p-4 mb-4" aria-labelledby="activity-data-title" data-tour="activity-data">
        <div class="activity-section-title"><span class="activity-section-icon" aria-hidden="true">01</span><div><h2 id="activity-data-title" class="h4 mb-1">Dados da atividade</h2><p>Defina as informações que serão apresentadas aos alunos.</p></div></div>
        <div class="row g-3">
            <div class="col-md-7"><label class="form-label" for="title">Título</label><input class="form-control" id="title" name="title" value="{{ old('title', $activity->title ?? '') }}" maxlength="180" required></div>
            <div class="col-md-5"><label class="form-label" for="classroom_id">Turma</label><select class="form-select" id="classroom_id" name="classroom_id" required @disabled($metadataOnly)><option value="">Selecione a turma</option>@foreach($classrooms as $classroom)<option value="{{ $classroom->id }}" @selected(old('classroom_id', $activity->classroom_id ?? request('classroom_id')) == $classroom->id) @disabled(! $classroom->is_active && (! $isEditing || $activity->classroom_id !== $classroom->id))>{{ $classroom->name }}{{ $classroom->is_active ? '' : ' (inativa — disponível apenas para salvar o rascunho)' }}</option>@endforeach</select>@if($metadataOnly)<input type="hidden" name="classroom_id" value="{{ $activity->classroom_id }}"><div class="form-text">A turma não pode ser alterada porque já existem submissões.</div>@endif</div>
            <div class="col-md-8"><label class="form-label" for="description">Instruções para os alunos</label><textarea class="form-control" id="description" name="description" rows="3" maxlength="5000">{{ old('description', $activity->description ?? '') }}</textarea></div>
            <div class="col-md-4"><label class="form-label" for="deadline_at">Prazo <span class="text-secondary fw-normal">(opcional)</span></label><input class="form-control" id="deadline_at" name="deadline_at" type="datetime-local" value="{{ old('deadline_at', $isEditing ? $activity->deadline_at?->format('Y-m-d\TH:i') : '') }}"><div class="form-text">Sem prazo, a atividade permanece aberta.</div></div>
        </div>
    </section>

    <details id="activity-settings" class="card activity-data-card activity-settings p-4 mb-4" @if($errors->hasAny(['grading_instructions', 'feedback_detail', 'intelligence_profile', 'grading_strictness'])) open @endif>
        <summary class="activity-settings-summary"><span class="activity-section-icon" aria-hidden="true">02</span><span><strong>Como corrigir com IA</strong><small>Preferências para questões dissertativas · ajuste quando precisar</small></span></summary>
        <div class="activity-settings-body" data-tour="grading-preferences">
            @include('teacher.grading._preferences')
            <label class="form-label" for="grading_instructions">Orientações do professor <span class="text-secondary fw-normal">(opcional)</span></label>
            <textarea class="form-control" id="grading_instructions" name="grading_instructions" rows="4" maxlength="2000" placeholder="Ex.: valorize a argumentação, aceite respostas equivalentes e forneça feedback objetivo.">{{ old('grading_instructions', $activity->grading_instructions ?? '') }}</textarea>
            <div class="form-text">A IA apenas sugere. Revise cada correção antes de publicar.</div>
        </div>
    </details>

    @if(! $metadataOnly)
    <section class="activity-questions-section" aria-labelledby="activity-questions-title">
        <div class="section-toolbar">
            <div><span class="eyebrow">Conteúdo da atividade</span><h2 id="activity-questions-title">Questões e contexto</h2><p>Adicione questões na ordem de leitura. Use blocos de contexto para apresentar textos de apoio.</p></div>
        </div>

        <div class="question-add-toolbar glass-surface" role="toolbar" aria-label="Adicionar questão">
            <div class="question-add-toolbar-copy">
                <span class="question-add-symbol" aria-hidden="true">+</span>
                <div><strong>Adicionar conteúdo</strong><small><span data-question-count>{{ count($rows) }}</span> <span data-question-count-label>{{ count($rows) === 1 ? 'item criado' : 'itens criados' }}</span></small></div>
            </div>
            <div class="question-add-actions">
                <button class="btn question-add-button question-add-essay" type="button" data-add-question="essay"><span aria-hidden="true">¶</span> Adicionar dissertativa</button>
                <button class="btn question-add-button question-add-choice" type="button" data-add-question="single_choice"><span aria-hidden="true">☷</span> Adicionar alternativa</button>
                <button class="btn question-add-button question-add-choice" type="button" data-add-question="multiple_choice"><span aria-hidden="true">☑</span> Múltipla escolha</button>
                <button class="btn question-add-button question-add-context" type="button" data-add-question="context"><span aria-hidden="true">▤</span> Bloco de contexto</button>
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
                            @include('teacher.activities._bank-correction', ['bankId' => $bankQuestion['id'], 'bankType' => $bankQuestion['type']->value, 'importCorrection' => $bankQuestion['import_correction']])
                        @endforeach
                    </div>
                @endif

                @forelse($bankQuestions as $bankQuestion)
                    <label class="bank-question-option">
                        <input class="form-check-input" type="checkbox" name="bank_questions[]" value="{{ $bankQuestion->id }}" @checked(in_array($bankQuestion->id, $selectedBank, true))>
                        <span><strong>{{ $bankQuestion->body }}</strong><small>{{ $bankQuestion->type->value === 'essay' ? 'Dissertativa' : 'Escolha única' }} · {{ number_format((float) $bankQuestion->max_score, 2, ',', '.') }} pontos</small></span>
                    </label>
                    @include('teacher.activities._bank-correction', ['bankId' => $bankQuestion->id, 'bankType' => $bankQuestion->type->value, 'importCorrection' => true])
                @empty
                    <p class="text-secondary mb-0">Nenhuma outra pergunta foi encontrada. Você pode criar perguntas diretamente nesta atividade.</p>
                @endforelse
                @if($bankQuestions->hasPages())
                    <div class="mt-3" data-bank-pagination>{{ $bankQuestions->links() }}</div>
                @endif
            </div>
        </details>
    </section>
    @else
    <section class="card p-4 mb-4" aria-labelledby="activity-questions-readonly-title">
        <h2 id="activity-questions-readonly-title" class="h4">Perguntas preservadas</h2>
        <p class="text-secondary">Esta atividade já foi aberta por aluno. Para preservar respostas, correções e notas, as perguntas não podem mais ser alteradas.</p>
        <ol class="mb-0">@foreach($activity->questions as $question)<li class="mb-2"><strong>{{ $question->body }}</strong> <span class="text-secondary">· {{ number_format((float) $question->max_score, 2, ',', '.') }} pontos</span></li>@endforeach</ol>
    </section>
    @endif

    <div id="activity-actions" class="activity-form-actions glass-surface">
        <div><strong>{{ $isEditing ? 'Atualizar atividade' : 'Finalizar criação' }}</strong><small>{{ $metadataOnly ? 'As entregas e perguntas existentes serão preservadas.' : ($isDraft ? 'O rascunho pode ser editado antes da publicação.' : 'As alterações serão aplicadas à atividade publicada.') }}</small></div>
        <div class="d-flex flex-wrap gap-2">
            @if($isDraft)
            <button class="btn btn-quiet" type="submit" name="intent" value="draft">Salvar rascunho</button>
            <button class="btn btn-preview" type="submit" name="intent" value="preview"><span aria-hidden="true">◉</span> Visualizar</button>
            <button class="btn btn-success" type="submit" name="intent" value="publish" data-confirm-publish>Salvar e publicar</button>
            @else
            @if(! $metadataOnly)<button class="btn btn-preview" type="submit" name="intent" value="preview"><span aria-hidden="true">◉</span> Visualizar</button>@endif
            <button class="btn btn-primary" type="submit" name="intent" value="metadata">Salvar alterações</button>
            @endif
        </div>
    </div>
</form>

<template id="activity-question-template-essay" data-question-template="essay">
    @include('teacher.activities._question-fields', ['index' => '__INDEX__', 'question' => ['type' => 'essay', 'max_score' => 1, 'rubric' => []]])
</template>
<template id="activity-question-template-choice" data-question-template="single_choice">
    @include('teacher.activities._question-fields', ['index' => '__INDEX__', 'question' => ['type' => 'single_choice', 'max_score' => 1, 'correct_option' => 0, 'options' => [[], [], [], []]]])
</template>
<template data-question-template="multiple_choice">
    @include('teacher.activities._question-fields', ['index' => '__INDEX__', 'question' => ['type' => 'multiple_choice', 'max_score' => 1, 'options' => [[], [], [], []]]])
</template>
<template data-question-template="context">
    @include('teacher.activities._question-fields', ['index' => '__INDEX__', 'question' => ['type' => 'context', 'max_score' => 0]])
</template>
@endsection
