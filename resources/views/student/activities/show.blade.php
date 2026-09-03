@extends('layouts.app')
@section('title', $activity->title.' · FormAI')
@section('content')
<div class="mb-4"><h1>{{ $activity->title }}</h1><p>{{ $activity->description }}</p><div class="alert alert-warning">{{ $activity->deadline_at ? 'Prazo: '.$activity->deadline_at->format('d/m/Y H:i').'.' : 'Esta atividade não possui prazo de entrega.' }} Depois do envio, somente o professor pode reabrir.</div></div>
@php($answers=$submission->answers->keyBy('activity_question_id'))
@foreach($activity->questions as $question)@php($answer=$answers->get($question->id))
<form class="card question-card p-4 mb-4" method="post" action="{{ route('student.answers.save',[$submission,$question]) }}" data-autosave>@csrf @method('PUT')<input type="hidden" name="version" value="{{ $answer?->version ?? 0 }}"><h2 class="h5">{{ $question->position }}. {{ $question->body }}</h2><p class="text-secondary">Vale {{ $question->max_score }} pontos</p>
@if($question->type === App\Domain\QuestionBank\Enums\QuestionType::Essay)<label class="form-label" for="answer-{{ $question->id }}">Sua resposta</label><textarea class="form-control" id="answer-{{ $question->id }}" name="response_text" rows="7" maxlength="30000">{{ $answer?->response_text }}</textarea>
@else<fieldset><legend class="visually-hidden">Escolha uma alternativa</legend>@foreach($question->options_snapshot as $option)<div class="form-check mb-2"><input class="form-check-input" id="q{{ $question->id }}-{{ $option['key'] }}" name="selected_option_key" type="radio" value="{{ $option['key'] }}" @checked($answer?->selected_option_key === $option['key'])><label class="form-check-label" for="q{{ $question->id }}-{{ $option['key'] }}">{{ $option['key'] }}. {{ $option['text'] }}</label></div>@endforeach</fieldset>@endif
<div class="autosave-status small text-secondary mt-2" data-save-status aria-live="polite">{{ $answer ? 'Rascunho salvo' : 'Ainda nao salvo' }}</div></form>
@endforeach
<form method="post" action="{{ route('student.submissions.submit',$submission) }}" data-confirm="Enviar definitivamente? Voce nao podera alterar as respostas.">@csrf<button class="btn btn-success btn-lg" type="submit">Enviar atividade</button></form>
@endsection
