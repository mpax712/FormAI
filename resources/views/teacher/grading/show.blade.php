@extends('layouts.app')

@section('title', 'Corrigir entrega · FormAI')

@section('content')
@php
    $activeAiRuns = $submission->answers->pluck('latestGradingRun')->filter(fn ($run) => $run && in_array($run->status, [App\Domain\Grading\Enums\GradingRunStatus::Pending, App\Domain\Grading\Enums\GradingRunStatus::Processing, App\Domain\Grading\Enums\GradingRunStatus::RetryableFailed], true));
@endphp
<div class="grading-page" @if($activeAiRuns->isNotEmpty()) data-ai-tracker data-status-url="{{ route('teacher.grading.ai-status', $submission) }}" @endif>
    <header class="grading-detail-header">
        <div>
            <a class="grading-back-link" href="{{ route('teacher.activities.show', $submission->activity) }}">← Voltar para as entregas</a>
            <span class="grading-eyebrow">Correção manual</span>
            <h1>Correção de {{ $submission->student->name }}</h1>
            <p>{{ $submission->activity->title }} · enviada {{ $submission->submitted_at?->format('d/m/Y H:i') }}</p>
        </div>
        <div class="grading-human-badge"><span aria-hidden="true">✓</span><div><small>Decisão final</small><strong>Sempre do professor</strong></div></div>
    </header>

    @if($activeAiRuns->isNotEmpty())
        <section class="ai-progress-panel ai-progress-wide mb-4" data-ai-progress role="status" aria-live="polite">
            <span class="ai-spinner" aria-hidden="true"></span>
            <div><strong data-ai-title>A IA ainda está analisando</strong><p data-ai-message>Você pode continuar a correção manual. As sugestões aparecerão automaticamente quando estiverem prontas.</p></div>
        </section>
    @endif

    @if(! $aiConfigured)
        <div class="alert alert-warning mb-4">A correção manual está disponível. Configure a <code>{{ $aiKeyName }}</code> para usar sugestões da IA.</div>
    @endif

    <form id="correcao-manual" method="post" action="{{ route('teacher.grading.review', $submission) }}">
        @csrf
        @method('PUT')

        @foreach($submission->answers as $answer)
            @php($run = $answer->latestGradingRun)
            @php($suggestion = $run?->suggestion)
            <article class="card grading-answer-card p-4 mb-4">
                <div class="grading-answer-head">
                    <div><span class="grading-eyebrow">Questão {{ $loop->iteration }}</span><h2>{{ $answer->activityQuestion->body }}</h2></div>
                    @if($answer->activityQuestion->type === App\Domain\QuestionBank\Enums\QuestionType::Essay && ! $suggestion && (! $run || $run->status === App\Domain\Grading\Enums\GradingRunStatus::PermanentlyFailed))
                        <button class="btn btn-sm btn-ai-question" type="submit" form="ai-answer-{{ $answer->id }}" data-ai-trigger @disabled(! $aiConfigured)><span aria-hidden="true">✦</span><span data-button-label>Corrigir esta questão com IA</span></button>
                    @endif
                </div>

                <div class="student-answer-box">
                    <strong>Resposta do aluno</strong>
                    <div>{{ $answer->response_text ?: $answer->selected_option_key }}</div>
                </div>

                @if($answer->activityQuestion->type === App\Domain\QuestionBank\Enums\QuestionType::SingleChoice)
                    <div class="objective-result"><span aria-hidden="true">✓</span>Correção automática incluída no subtotal objetivo de {{ $submission->objective_score }} pontos.</div>
                @else
                    @if($suggestion)
                        <section class="ai-suggestion" aria-label="Sugestão da inteligência artificial">
                            <div class="ai-suggestion-head"><div><span aria-hidden="true">✦</span><strong>Sugestão da IA</strong></div><span>Confiança: {{ round((float) $suggestion->confidence * 100) }}%</span></div>
                            <p class="ai-score"><strong>{{ $suggestion->score }}</strong><span>/ {{ $answer->activityQuestion->max_score }} pontos sugeridos</span></p>
                            @if($suggestion->criterion_scores)
                                <ul>
                                    @foreach($suggestion->criterion_scores as $criterion)
                                        <li><strong>{{ $criterion['criterion'] ?? 'Critério' }}:</strong> {{ $criterion['score'] ?? 0 }} — {{ $criterion['justification'] ?? '' }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if($suggestion->evidence)<p><strong>Evidências:</strong> {{ implode(' · ', $suggestion->evidence) }}</p>@endif
                            <p><strong>Feedback sugerido:</strong> {{ $suggestion->feedback }}</p>
                            @if($suggestion->warnings)<p class="text-danger mb-0"><strong>Atenção:</strong> {{ implode(' · ', $suggestion->warnings) }}</p>@endif
                            <small>Revise a nota e o feedback antes de salvar.</small>
                        </section>
                    @elseif($run && in_array($run->status, [App\Domain\Grading\Enums\GradingRunStatus::Pending, App\Domain\Grading\Enums\GradingRunStatus::Processing, App\Domain\Grading\Enums\GradingRunStatus::RetryableFailed], true))
                        <div class="ai-inline-loading"><span class="ai-spinner ai-spinner-sm" aria-hidden="true"></span><span>A IA está analisando esta resposta. Você pode corrigi-la manualmente enquanto aguarda.</span></div>
                    @elseif($run?->status === App\Domain\Grading\Enums\GradingRunStatus::PermanentlyFailed)
                        <div class="ai-error-panel mb-3" role="alert"><span aria-hidden="true">!</span><div><strong>A IA não conseguiu corrigir esta questão</strong><p>{{ $run->error_message ?: 'O pedido foi encerrado. Tente novamente ou conclua a correção manualmente.' }}</p></div></div>
                    @else
                        <div class="manual-hint"><span aria-hidden="true">✎</span>Preencha a nota e o feedback ou solicite uma sugestão da IA acima.</div>
                    @endif

                    <div class="row grading-fields">
                        <div class="col-md-3 mb-3">
                            <label class="form-label" for="score-{{ $answer->id }}">Nota <small>máx. {{ $answer->activityQuestion->max_score }}</small></label>
                            <input class="form-control" id="score-{{ $answer->id }}" name="grades[{{ $answer->id }}][score]" type="number" min="0" max="{{ $answer->activityQuestion->max_score }}" step="0.01" value="{{ old("grades.{$answer->id}.score", $answer->gradingDecision?->score ?? $suggestion?->score) }}" required>
                        </div>
                        <div class="col-md-9 mb-3">
                            <label class="form-label" for="feedback-{{ $answer->id }}">Feedback ao aluno</label>
                            <textarea class="form-control" id="feedback-{{ $answer->id }}" name="grades[{{ $answer->id }}][feedback]" rows="4">{{ old("grades.{$answer->id}.feedback", $answer->gradingDecision?->feedback ?? $suggestion?->feedback) }}</textarea>
                        </div>
                    </div>
                @endif
            </article>
        @endforeach

        <div class="grading-save-bar"><div><strong>Finalize sua revisão</strong><small>A nota só será enviada ao aluno quando você publicar o resultado.</small></div><button class="btn btn-primary" type="submit">Salvar revisão humana</button></div>
    </form>

    @foreach($submission->answers as $answer)
        @if($answer->activityQuestion->type === App\Domain\QuestionBank\Enums\QuestionType::Essay)
            <form id="ai-answer-{{ $answer->id }}" method="post" action="{{ route('teacher.grading.ai-answer', [$submission, $answer]) }}" data-ai-submit data-ai-label="Analisando questão...">@csrf</form>
        @endif
    @endforeach

    <div class="d-flex gap-2 mt-4">
        @if($submission->status === App\Domain\Submissions\Enums\SubmissionStatus::Reviewed)
            <form method="post" action="{{ route('teacher.grading.release', $submission) }}" data-confirm="Publicar nota e feedback para o aluno?">@csrf<button class="btn btn-success" type="submit">Publicar resultado</button></form>
        @endif
        @if($submission->status !== App\Domain\Submissions\Enums\SubmissionStatus::Released)
            <form method="post" action="{{ route('teacher.grading.reopen', $submission) }}">@csrf<button class="btn btn-outline-secondary" type="submit">Reabrir entrega</button></form>
        @endif
    </div>
</div>
@endsection
