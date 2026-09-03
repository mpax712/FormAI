@extends('layouts.app')
@section('title', 'Resultado · '.$submission->activity->title)
@section('content')
<div class="card p-4 mb-4"><span class="text-secondary">Resultado publicado</span><h1>{{ $submission->activity->title }}</h1><div class="metric">{{ number_format((float)$submission->final_score,2,',','.') }} / {{ number_format((float)$submission->activity->total_score,2,',','.') }}</div></div>
@foreach($submission->answers as $answer)<article class="card p-4 mb-3"><h2 class="h5">{{ $answer->activityQuestion->body }}</h2><p><strong>Sua resposta:</strong> {{ $answer->response_text ?: $answer->selected_option_key }}</p>@if($answer->gradingDecision)<p><strong>Nota:</strong> {{ $answer->gradingDecision->score }} / {{ $answer->activityQuestion->max_score }}</p><p><strong>Feedback:</strong> {{ $answer->gradingDecision->feedback ?: 'Sem comentario adicional.' }}</p>@else<p class="text-secondary">Questao objetiva corrigida automaticamente.</p>@endif</article>@endforeach
@endsection
