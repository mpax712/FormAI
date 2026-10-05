@extends('layouts.app')
@section('title', $activity->title.' · FormAI')
@section('content')
<div class="student-activity-hero mb-4">
    <a class="student-back-link" href="{{ route('student.activities.index') }}">← Minhas atividades</a>
    <h1>{{ $activity->title }}</h1>
    @if($activity->description)<div class="question-rich-text activity-description">{{ $activity->description }}</div>@endif
    <div class="activity-deadline-note">{{ $activity->deadline_at ? 'Prazo: '.$activity->deadline_at->format('d/m/Y H:i').'.' : 'Esta atividade não possui prazo de entrega.' }} Depois do envio, somente o professor pode reabrir.</div>
</div>
<div class="student-progress-bar">
    <span><strong data-student-progress>{{ $submission->answers->filter(fn ($answer) => filled($answer->response_text) || filled($answer->selected_option_key))->count() }}</strong> de {{ $activity->questions->filter(fn ($question) => $question->type !== App\Domain\QuestionBank\Enums\QuestionType::Context)->count() }} questões respondidas</span>
    <span>Suas respostas são salvas automaticamente.</span>
</div>
<x-context-help title="Como salvar e enviar minhas respostas?">As respostas são salvas enquanto você preenche a atividade. Aguarde a indicação “Rascunho salvo” em cada questão. O botão “Enviar atividade” faz a entrega final; revise tudo antes de confirmar.</x-context-help>
@php($answers = $submission->answers->keyBy('activity_question_id'))
@foreach($activity->questions as $question)
    @include('student.activities._question', ['isResult' => false])
@endforeach
<form class="student-submit-form" method="post" action="{{ route('student.submissions.submit', $submission) }}" data-confirm="Enviar definitivamente? Você não poderá alterar as respostas.">
    @csrf
    <button class="btn btn-success btn-lg" type="submit">Enviar atividade</button>
    <span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span>
</form>
@endsection
