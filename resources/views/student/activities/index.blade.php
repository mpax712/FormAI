@extends('layouts.app')
@section('title', 'Minhas atividades · FormAI')
@section('content')
<header class="student-list-header">
    <span class="grading-eyebrow">Área do aluno</span>
    <h1>Minhas atividades</h1>
    <p>Veja o que falta fazer e acompanhe os resultados publicados.</p>
</header>
<div class="student-activity-list" data-tour="student-activity-list">
    @forelse($activities as $activity)
        @php($submission = $activity->submissions->first())
        @php($canAnswer = $activity->deadline_at === null || $activity->deadline_at->isFuture() || ($submission?->reopened_until?->isFuture() ?? false))
        <article class="card student-list-card">
            <div class="student-list-card-copy">
                <div class="student-list-kicker"><span>{{ $activity->classroom->name }}</span><x-workflow-status :value="$submission?->status->value ?? 'Não iniciada'" /></div>
                <h2>{{ $activity->title }}</h2>
                <p>{{ $activity->deadline_at ? 'Prazo: '.$activity->deadline_at->format('d/m/Y H:i') : 'Sem prazo de entrega' }}</p>
            </div>
            <div class="student-list-card-action">
                @if($submission?->status === App\Domain\Submissions\Enums\SubmissionStatus::Released)
                    <a class="btn btn-success" href="{{ route('student.submissions.result', $submission) }}">Ver resultado</a>
                @elseif((! $submission || $submission->status === App\Domain\Submissions\Enums\SubmissionStatus::Draft) && $canAnswer)
                    <a class="btn btn-primary" href="{{ route('student.activities.show', $activity) }}">{{ $submission ? 'Continuar' : 'Começar' }}</a>
                @elseif(! $submission || $submission->status === App\Domain\Submissions\Enums\SubmissionStatus::Draft)
                    <span class="student-list-state">Prazo encerrado</span>
                @else
                    <span class="student-list-state">Entrega recebida · aguardando resultado</span>
                @endif
            </div>
        </article>
    @empty
        <div class="card student-list-empty"><strong>Nenhuma atividade disponível por enquanto.</strong><p>Quando seu professor publicar uma atividade, ela aparecerá aqui.</p></div>
    @endforelse
</div>
<div class="mt-4">{{ $activities->links() }}</div>
@endsection
