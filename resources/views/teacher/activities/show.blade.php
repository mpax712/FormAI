@extends('layouts.app')
@section('title', $activity->title.' · FormAI')

@section('content')
<header class="activity-detail-header">
    <div>
        <span class="grading-eyebrow">Visão da atividade</span>
        <h1>{{ $activity->title }}</h1>
        <p>{{ $activity->classroom->name }} · {{ $activity->deadline_at ? 'prazo '.$activity->deadline_at->format('d/m/Y H:i') : 'sem prazo de entrega' }}</p>
    </div>
    @if($activity->status === App\Domain\Activities\Enums\ActivityStatus::Draft)
        <a class="btn btn-primary" href="{{ route('teacher.activities.edit', $activity) }}">Continuar edição</a>
    @endif
</header>

<div class="row g-4">
    <div class="col-lg-7">
        <section class="card p-4 grading-question-list">
            <div class="section-heading">
                <div><span class="grading-eyebrow">Conteúdo</span><h2>Questões publicadas</h2></div>
                <span class="grading-count">{{ $activity->questions->count() }}</span>
            </div>
            @forelse($activity->questions as $question)
                <article class="published-question">
                    <span class="published-question-number">{{ $question->position }}</span>
                    <div><strong>{{ $question->body }}</strong><small>{{ $question->type->value === 'essay' ? 'Dissertativa' : 'Objetiva' }}</small></div>
                    <span class="published-question-score">{{ $question->max_score }} pts</span>
                </article>
            @empty
                <p class="text-secondary mb-0">O rascunho ainda não possui questões publicadas.</p>
            @endforelse
        </section>
    </div>

    <div class="col-lg-5">
        <section class="card p-4 submissions-panel">
            <div class="section-heading">
                <div><span class="grading-eyebrow">Avaliação</span><h2>Entregas</h2></div>
                <span class="grading-count">{{ $activity->submissions->count() }}</span>
            </div>
            <p class="text-secondary submissions-intro">Escolha correção manual ou solicite a análise completa da IA antes de abrir cada entrega.</p>

            <div class="submission-stack">
                @forelse($activity->submissions as $submission)
                    @php
                        $essayAnswers = $submission->answers->filter(fn ($answer) => $answer->activityQuestion->type === App\Domain\QuestionBank\Enums\QuestionType::Essay);
                        $runs = $essayAnswers->pluck('latestGradingRun')->filter();
                        $activeRuns = $runs->filter(fn ($run) => in_array($run->status, [App\Domain\Grading\Enums\GradingRunStatus::Pending, App\Domain\Grading\Enums\GradingRunStatus::Processing, App\Domain\Grading\Enums\GradingRunStatus::RetryableFailed], true));
                        $failedRuns = $runs->where('status', App\Domain\Grading\Enums\GradingRunStatus::PermanentlyFailed);
                        $needsAi = $runs->count() < $essayAnswers->count() || $failedRuns->isNotEmpty();
                        $statusLabel = match ($submission->status->value) {
                            'submitted' => 'Aguardando correção',
                            'processing' => 'IA analisando',
                            'reviewed' => 'Revisada',
                            'released' => 'Publicada',
                            default => 'Rascunho',
                        };
                    @endphp
                    <article class="submission-card" @if($activeRuns->isNotEmpty()) data-ai-tracker data-status-url="{{ route('teacher.grading.ai-status', $submission) }}" @endif>
                        <div class="submission-card-head">
                            <div class="student-identity"><span aria-hidden="true">{{ mb_strtoupper(mb_substr($submission->student->name, 0, 1)) }}</span><div><strong>{{ $submission->student->name }}</strong><small>Enviada {{ $submission->submitted_at?->format('d/m/Y H:i') }}</small></div></div>
                            <span class="submission-status submission-status-{{ $submission->status->value }}">{{ $statusLabel }}</span>
                        </div>

                        <div class="ai-progress-panel {{ $activeRuns->isEmpty() ? 'd-none' : '' }}" data-ai-progress role="status" aria-live="polite">
                            <span class="ai-spinner" aria-hidden="true"></span>
                            <div><strong data-ai-title>IA corrigindo a atividade</strong><p data-ai-message>Aguarde enquanto as respostas são analisadas. Você pode entrar na correção manual a qualquer momento.</p></div>
                        </div>

                        <div class="ai-error-panel {{ $failedRuns->isEmpty() ? 'd-none' : '' }}" data-ai-error role="alert">
                            <span aria-hidden="true">!</span>
                            <div><strong>Não foi possível concluir a correção com IA</strong><p data-ai-error-message>{{ $failedRuns->first()?->error_message ?: 'O pedido foi encerrado. Você ainda pode corrigir esta entrega manualmente.' }}</p></div>
                        </div>

                        @if($submission->status->value !== 'draft')
                            <div class="submission-actions">
                                <a class="btn btn-outline-primary" href="{{ route('teacher.grading.show', $submission) }}">Corrigir manualmente</a>
                                @if($essayAnswers->isNotEmpty() && $activeRuns->isEmpty() && $needsAi)
                                    <form method="post" action="{{ route('teacher.grading.ai-all', $submission) }}" data-ai-submit data-ai-label="Corrigindo com IA...">
                                        @csrf
                                        <button class="btn btn-primary" type="submit" @disabled(! $aiConfigured)><span class="ai-button-spark" aria-hidden="true">✦</span><span data-button-label>Corrigir com IA</span></button>
                                    </form>
                                @elseif($essayAnswers->isNotEmpty() && $activeRuns->isEmpty())
                                    <a class="btn btn-ai-ready" href="{{ route('teacher.grading.show', $submission) }}"><span aria-hidden="true">✓</span> Sugestões prontas</a>
                                @endif
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="empty-submissions"><strong>Nenhuma entrega iniciada</strong><p>As respostas dos alunos aparecerão aqui.</p></div>
                @endforelse
            </div>

            @if(! $aiConfigured)
                <div class="alert alert-warning mt-3 mb-0">Configure a <code>{{ $aiKeyName }}</code> para habilitar a correção com IA. A correção manual continua disponível.</div>
            @endif
        </section>
    </div>
</div>
@endsection
