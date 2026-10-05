@extends('layouts.app')
@section('title', $activity->title.' · FormAI')

@section('content')
<header class="activity-detail-header">
    <div>
        <span class="grading-eyebrow">Visão da atividade</span>
        <h1>{{ $activity->title }}</h1>
        <p>{{ $activity->classroom->name }} · {{ $activity->deadline_at ? 'prazo '.$activity->deadline_at->format('d/m/Y H:i') : 'sem prazo de entrega' }}</p>
    </div>
    <a class="btn btn-primary" href="{{ route('teacher.activities.edit', $activity) }}">Editar atividade</a>
</header>

<div class="v2-deliveries" data-deliveries>
    <div>
        <details class="card p-4 grading-question-list mb-4"><summary>Questões da atividade ({{ $activity->questions->count() }})</summary><div class="mt-3">
            <div class="section-heading">
                <div><span class="grading-eyebrow">Conteúdo</span><h2>Questões publicadas</h2></div>
                <span class="grading-count">{{ $activity->questions->count() }}</span>
            </div>
            @forelse($activity->questions as $question)
                <article class="published-question">
                    <span class="published-question-number">{{ $question->position }}</span>
                    <div><strong class="question-rich-text d-block">{{ $question->body }}</strong><small>{{ match($question->type) { App\Domain\QuestionBank\Enums\QuestionType::Context => 'Texto de apoio', App\Domain\QuestionBank\Enums\QuestionType::MultipleChoice => 'Múltipla escolha', App\Domain\QuestionBank\Enums\QuestionType::Essay => 'Dissertativa', default => 'Escolha única' } }}</small></div>
                    @if($question->type !== App\Domain\QuestionBank\Enums\QuestionType::Context)<span class="published-question-score">{{ $question->max_score }} pts</span>@endif
                </article>
            @empty
                <p class="text-secondary mb-0">O rascunho ainda não possui questões publicadas.</p>
            @endforelse
        </div></details>
    </div>

    <div>
        <section class="card p-4 submissions-panel">
            <div class="section-heading">
                <div><span class="grading-eyebrow">Avaliação</span><h2>Entregas</h2></div>
                <span class="grading-count">{{ $activity->submissions->count() }}</span>
            </div>
            @if($activity->submissions->contains(fn ($submission) => $submission->status === App\Domain\Submissions\Enums\SubmissionStatus::Released))
                <a class="btn btn-outline-primary mb-3" href="{{ route('teacher.grading.activity-results.edit', $activity) }}">Editar resultados da atividade</a>
            @endif
            <p class="text-secondary submissions-intro">Escolha correção manual ou solicite a análise completa da IA antes de abrir cada entrega.</p>

            <div class="v2-filters">
                <label>Buscar aluno<input class="form-control" type="search" data-delivery-search placeholder="Nome do aluno"></label>
                <label>Situação<select class="form-select" data-delivery-filter><option value="all">Todas</option><option value="submitted">Aguardando correção</option><option value="active">IA em andamento</option><option value="reviewed">Revisadas</option><option value="released">Publicadas</option><option value="failed">Falha de IA</option></select></label>
            </div>
            <form id="selected-deliveries" method="post" action="{{ route('teacher.grading.ai-selected', $activity) }}" data-ai-submit data-ai-batch>
                @csrf
                <div class="v2-batch-toolbar"><label><input type="checkbox" data-select-visible> Selecionar entregas visíveis</label><span data-selection-count role="status" aria-live="polite">0 entregas · 0 questões dissertativas</span><button type="button" class="btn btn-outline-secondary" data-clear-selection>Limpar seleção</button></div>
                <details class="v2-batch-settings"><summary>Configuração do lote selecionado</summary>@include('teacher.grading._preferences')</details>
                <button class="btn btn-primary mb-3" type="submit" data-batch-submit data-ai-enabled="{{ $aiConfigured ? '1' : '0' }}" disabled>Corrigir selecionados com IA</button>
            </form>
            <div class="submission-stack">
                <details class="v2-all-deliveries"><summary>Correção de toda a atividade</summary><p>Esta ação inclui todas as entregas elegíveis da atividade, independentemente dos filtros ou da seleção.</p>
                <form method="post" action="{{ route('teacher.grading.ai-activity', $activity) }}" data-ai-submit data-ai-confirm="Solicitar IA para todas as entregas elegíveis desta atividade, incluindo as ocultas pelos filtros?">
                    @include('teacher.grading._preferences')
                    @csrf
                    <button class="btn btn-primary w-100 mb-3" @disabled(! $aiConfigured) type="submit">Corrigir todos com IA</button>
                </form></details>
                <p data-no-deliveries hidden class="v2-empty">Nenhuma entrega corresponde à busca e aos filtros.</p>
                @forelse($activity->submissions as $submission)
                    @php
                        $essayAnswers = $submission->answers->filter(fn ($answer) => $answer->activityQuestion->type === App\Domain\QuestionBank\Enums\QuestionType::Essay);
                        $runs = $essayAnswers->pluck('latestGradingRun')->filter();
                        $activeRuns = $runs->filter(fn ($run) => in_array($run->status, [App\Domain\Grading\Enums\GradingRunStatus::Pending, App\Domain\Grading\Enums\GradingRunStatus::Processing, App\Domain\Grading\Enums\GradingRunStatus::RetryableFailed], true));
                        $retryingRuns = $runs->where('status', App\Domain\Grading\Enums\GradingRunStatus::RetryableFailed);
                        $failedRuns = $runs->where('status', App\Domain\Grading\Enums\GradingRunStatus::PermanentlyFailed);
                        $needsAi = $runs->count() < $essayAnswers->count() || $failedRuns->isNotEmpty();
                        $statusLabel = $retryingRuns->isNotEmpty() ? 'Tentando novamente' : match ($submission->status->value) {
                            'submitted' => 'Aguardando correção',
                            'processing' => $activeRuns->isNotEmpty() ? 'IA em andamento' : 'Aguardando revisão',
                            'reviewed' => 'Revisada',
                            'released' => 'Publicada',
                            default => 'Rascunho',
                        };
                    @endphp
                    @php($eligible = $essayAnswers->isNotEmpty() && $activeRuns->isEmpty() && in_array($submission->status->value, ['submitted', 'processing']))
                    <article data-delivery data-student-name="{{ $submission->student->name }}" data-delivery-state="{{ $activeRuns->isNotEmpty() ? 'active' : ($submission->status->value === 'processing' ? 'submitted' : $submission->status->value) }}" data-has-failure="{{ $failedRuns->isNotEmpty() ? '1' : '0' }}" class="submission-card" @if($activeRuns->isNotEmpty()) data-ai-tracker data-status-url="{{ route('teacher.grading.ai-status', $submission) }}" @endif>
                        <div class="submission-card-head">
                            @if($eligible)<label class="v2-selection"><input type="checkbox" name="submission_ids[]" value="{{ $submission->public_id }}" form="selected-deliveries" data-delivery-select data-essay-count="{{ $essayAnswers->count() }}" @disabled(! $aiConfigured)><span class="visually-hidden">Selecionar {{ $submission->student->name }}</span></label>@endif
                            <div class="student-identity"><span aria-hidden="true">{{ mb_strtoupper(mb_substr($submission->student->name, 0, 1)) }}</span><div><strong>{{ $submission->student->name }}</strong><small>Enviada {{ $submission->submitted_at?->format('d/m/Y H:i') }}</small></div></div>
                            <span class="submission-status submission-status-{{ $submission->status->value }}">{{ $statusLabel }}</span>
                        </div>

                        <div class="ai-progress-panel {{ $activeRuns->isEmpty() ? 'd-none' : '' }}" data-ai-progress role="status" aria-live="polite">
                            <span class="ai-spinner" aria-hidden="true"></span>
                            <div><strong data-ai-title>{{ $retryingRuns->isNotEmpty() ? 'Tentando novamente' : 'IA corrigindo a atividade' }}</strong><p data-ai-message>{{ $retryingRuns->isNotEmpty() ? 'Aguardando capacidade ou uma nova tentativa. Consulte o andamento abaixo.' : 'Aguarde enquanto as respostas são analisadas. Você pode entrar na correção manual a qualquer momento.' }}</p></div>
                        </div>

                        <div class="ai-error-panel {{ $failedRuns->isEmpty() ? 'd-none' : '' }}" data-ai-error role="alert">
                            <span aria-hidden="true">!</span>
                            <div><strong>Não foi possível concluir a correção com IA</strong><p data-ai-error-message>{{ $failedRuns->first()?->error_message ?: 'O pedido foi encerrado. Você ainda pode corrigir esta entrega manualmente.' }}</p></div>
                        </div>

                        @if($submission->status->value !== 'draft')
                            <div class="submission-actions">
                                <a class="btn btn-outline-primary" href="{{ route('teacher.grading.show', $submission) }}">{{ $submission->status === App\Domain\Submissions\Enums\SubmissionStatus::Released ? 'Editar e republicar' : 'Corrigir manualmente' }}</a>
                                @if($essayAnswers->isNotEmpty() && $activeRuns->isEmpty() && in_array($submission->status->value, ['submitted', 'processing']))
                                    <details class="v2-individual-ai"><summary>Corrigir com IA · opções individuais</summary><form method="post" action="{{ route('teacher.grading.ai-all', $submission) }}" data-ai-submit data-ai-label="Corrigindo com IA...">
                                        @include('teacher.grading._preferences')
                                        @csrf
                                        <button class="btn btn-primary" type="submit" @disabled(! $aiConfigured)><span class="ai-button-spark" aria-hidden="true">✦</span><span data-button-label>Corrigir com IA</span></button>
                                    </form></details>
                                @endif
                                @if($essayAnswers->isNotEmpty() && $activeRuns->isEmpty() && ! $needsAi)
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
            @elseif($aiRuntimeWarning)
                <div class="alert alert-warning mt-3 mb-0">{{ $aiRuntimeWarning }} A correção manual continua disponível.</div>
            @endif
        </section>
    </div>
</div>
@endsection
