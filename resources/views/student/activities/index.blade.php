@extends('layouts.app')
@section('title', 'Minhas atividades · FormAI')
@section('content')
<h1>Minhas atividades</h1>
<p class="text-secondary">Uma entrega por atividade. Você pode salvar rascunhos antes do envio.</p>
<div class="row g-3">
    @forelse($activities as $activity)
        @php($submission = $activity->submissions->first())
        @php($canAnswer = $activity->deadline_at === null || $activity->deadline_at->isFuture() || ($submission?->reopened_until?->isFuture() ?? false))
        <div class="col-md-6">
            <div class="card p-4 h-100">
                <div class="d-flex justify-content-between">
                    <h2 class="h4">{{ $activity->title }}</h2>
                    <span class="badge text-bg-secondary">{{ $submission?->status->value ?? 'não iniciada' }}</span>
                </div>
                <p>{{ $activity->classroom->name }}</p>
                <p class="text-secondary">Prazo: {{ $activity->deadline_at?->format('d/m/Y H:i') ?? 'Sem prazo' }}</p>
                @if($submission?->status === App\Domain\Submissions\Enums\SubmissionStatus::Released)
                    <a class="btn btn-success mt-auto" href="{{ route('student.submissions.result', $submission) }}">Ver resultado</a>
                @elseif((! $submission || $submission->status === App\Domain\Submissions\Enums\SubmissionStatus::Draft) && $canAnswer)
                    <a class="btn btn-primary mt-auto" href="{{ route('student.activities.show', $activity) }}">Responder</a>
                @elseif(! $submission || $submission->status === App\Domain\Submissions\Enums\SubmissionStatus::Draft)
                    <p class="text-danger mt-auto mb-0">Prazo encerrado.</p>
                @else
                    <p class="text-success mt-auto mb-0">Entrega recebida. Aguarde a publicação.</p>
                @endif
            </div>
        </div>
    @empty
        <div class="alert alert-info">Nenhuma atividade disponível.</div>
    @endforelse
</div>
<div class="mt-4">{{ $activities->links() }}</div>
@endsection
