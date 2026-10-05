@extends('layouts.app')
@section('title', 'Resultado · '.$submission->activity->title)
@section('content')
@php($activity = $submission->activity)
@php($answers = $submission->answers->keyBy('activity_question_id'))
<div class="student-activity-hero student-result-hero mb-4">
    <a class="student-back-link" href="{{ route('student.activities.index') }}">← Minhas atividades</a>
    <span class="question-type-label">Resultado publicado</span>
    <h1>{{ $activity->title }}</h1>
    @if($activity->description)<div class="question-rich-text activity-description">{{ $activity->description }}</div>@endif
    <p class="student-final-score mb-0">Sua nota: <strong>{{ number_format((float) $submission->final_score, 2, ',', '.') }} / {{ number_format((float) $activity->total_score, 2, ',', '.') }}</strong></p>
</div>
@foreach($activity->questions as $question)
    @include('student.activities._question', ['isResult' => true])
@endforeach
@endsection
