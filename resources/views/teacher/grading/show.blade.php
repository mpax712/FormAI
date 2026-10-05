@extends('layouts.app')

@section('title', 'Corrigir entrega · FormAI')

@section('content')
@php
    $activeAiRuns = $submission->answers->pluck('latestGradingRun')->filter(fn ($run) => $run && in_array($run->status, [App\Domain\Grading\Enums\GradingRunStatus::Pending, App\Domain\Grading\Enums\GradingRunStatus::Processing, App\Domain\Grading\Enums\GradingRunStatus::RetryableFailed], true));
    $retryingAiRuns = $activeAiRuns->where('status', App\Domain\Grading\Enums\GradingRunStatus::RetryableFailed);
@endphp
<div class="grading-page" @if($activeAiRuns->isNotEmpty()) data-ai-tracker data-status-url="{{ route('teacher.grading.ai-status', $submission) }}" @endif>
    <header class="grading-detail-header">
        <div>
            <a class="grading-back-link" href="{{ route('teacher.activities.show', $submission->activity) }}">← Voltar para as entregas</a>
            <span class="grading-eyebrow">Correção manual</span>
            <h1>Correção de {{ $submission->student->name }}</h1>
            <p>{{ $submission->activity->title }} · enviada {{ $submission->submitted_at?->format('d/m/Y H:i') }}</p>
            <nav class="grading-delivery-nav" aria-label="Navegar entre entregas">
                @if($previousDelivery)
                    <a class="btn btn-outline-primary" data-grading-nav href="{{ route('teacher.grading.show', $previousDelivery) }}">← Anterior</a>
                @else
                    <span class="btn btn-outline-secondary disabled" aria-disabled="true">← Anterior</span>
                @endif
                <span class="grading-delivery-position">Entrega {{ $deliveryPosition === false ? '—' : $deliveryPosition + 1 }} de {{ $deliveryIds->count() }}</span>
                @if($nextDelivery)
                    <a class="btn btn-outline-primary" data-grading-nav href="{{ route('teacher.grading.show', $nextDelivery) }}">Próxima →</a>
                @else
                    <span class="btn btn-outline-secondary disabled" aria-disabled="true">Próxima →</span>
                @endif
            </nav>
        </div>
        <div class="grading-human-badge"><span aria-hidden="true">✓</span><div><small>Decisão final</small><strong>Sempre do professor</strong></div></div>
    </header>

    @if($activeAiRuns->isNotEmpty())
        <section class="ai-progress-panel ai-progress-wide mb-4" data-ai-progress role="status" aria-live="polite">
            <span class="ai-spinner" aria-hidden="true"></span>
            <div><strong data-ai-title>{{ $retryingAiRuns->isNotEmpty() ? 'Tentando novamente' : 'A IA ainda está analisando' }}</strong><p data-ai-message>{{ $retryingAiRuns->isNotEmpty() ? 'Aguardando capacidade ou uma nova tentativa. O andamento será atualizado automaticamente.' : 'Você pode continuar a correção manual. As sugestões aparecerão automaticamente quando estiverem prontas.' }}</p></div>
        </section>
    @endif

    @if(! $aiConfigured)
        <div class="alert alert-warning mb-4">A correção manual está disponível. Configure a <code>{{ $aiKeyName }}</code> para usar sugestões da IA.</div>
    @elseif($aiRuntimeWarning)
        <div class="alert alert-warning mb-4">{{ $aiRuntimeWarning }} A correção manual continua disponível.</div>
    @endif

    <form id="correcao-manual" method="post" action="{{ route('teacher.grading.review', $submission) }}" data-review-form>
        @csrf
        @method('PUT')

        @foreach($submission->answers as $answer)
            @php
                $run = $answer->latestGradingRun;
                $suggestion = $run?->suggestion;
                $correctKeys = collect($answer->activityQuestion->options_snapshot ?? [])->filter(fn ($option) => (bool) ($option['is_correct'] ?? false))->pluck('key')->sort()->values()->all();
                $selectedKeys = explode(',', (string) $answer->selected_option_key);
                sort($selectedKeys);
                $automaticScore = $selectedKeys === $correctKeys ? $answer->activityQuestion->max_score : 0;
            @endphp
            <article class="card grading-answer-card p-4 mb-4">
                <div class="grading-answer-head">
                    <div><span class="grading-eyebrow">Questão {{ $answer->activityQuestion->position }}</span><h2 class="question-rich-text">{{ $answer->activityQuestion->body }}</h2></div>
                    @if($answer->activityQuestion->type === App\Domain\QuestionBank\Enums\QuestionType::Essay && in_array($submission->status->value, ['submitted', 'processing']))
                        <button class="btn btn-sm btn-ai-question" type="submit" form="ai-answer-{{ $answer->id }}" data-ai-trigger @disabled(! $aiConfigured)><span aria-hidden="true">✦</span><span data-button-label>Corrigir esta questão com IA</span></button>
                    @endif
                </div>

                @if($answer->activityQuestion->type === App\Domain\QuestionBank\Enums\QuestionType::Essay && in_array($submission->status->value, ['submitted', 'processing']))
                    @include('teacher.grading._preferences', ['preferenceForm' => 'ai-answer-'.$answer->id, 'preferenceActivity' => $submission->activity])
                @endif
                <div class="student-answer-box">
                    <strong>Resposta do aluno</strong>
                    <div class="question-rich-text">{{ $answer->activityQuestion->type === App\Domain\QuestionBank\Enums\QuestionType::Essay ? $answer->response_text : collect($answer->activityQuestion->options_snapshot ?? [])->whereIn('key', explode(',', (string) $answer->selected_option_key))->map(fn ($option) => $option['key'].'. '.$option['text'])->implode('; ') }}</div>
                </div>

                @if(in_array($answer->activityQuestion->type, [App\Domain\QuestionBank\Enums\QuestionType::SingleChoice, App\Domain\QuestionBank\Enums\QuestionType::MultipleChoice], true))
                    <div class="objective-result"><span aria-hidden="true">✓</span>Correção automática: {{ number_format((float) $automaticScore, 2, ',', '.') }} / {{ number_format((float) $answer->activityQuestion->max_score, 2, ',', '.') }} pontos. Você pode ajustar a nota e explicar o motivo ao aluno.</div>
                    <p class="small text-secondary">Gabarito: {{ implode(', ', $correctKeys) }}</p>
                    <div class="row grading-fields mt-3">
                        <div class="col-md-3 mb-3">
                            <label class="form-label" for="score-{{ $answer->id }}">Nota <small>máx. {{ $answer->activityQuestion->max_score }}</small></label>
                            <input class="form-control" id="score-{{ $answer->id }}" name="grades[{{ $answer->id }}][score]" type="number" min="0" max="{{ $answer->activityQuestion->max_score }}" step="0.01" value="{{ old("grades.{$answer->id}.score", $answer->gradingDecision?->score ?? $automaticScore) }}" required>
                        </div>
                        <div class="col-md-9 mb-3">
                            <label class="form-label" for="feedback-{{ $answer->id }}">Feedback ao aluno</label>
                            <textarea class="form-control" id="feedback-{{ $answer->id }}" name="grades[{{ $answer->id }}][feedback]" rows="3">{{ old("grades.{$answer->id}.feedback", $answer->gradingDecision?->feedback) }}</textarea>
                        </div>
                    </div>
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
                            @if($suggestion->teacher_feedback)<div class="alert alert-info"><strong>Análise privada para o professor</strong><p class="mb-0" style="white-space: pre-wrap">{{ $suggestion->teacher_feedback }}</p></div>@endif
                            <p><strong>Feedback sugerido ao aluno:</strong> {{ $suggestion->feedback }}</p>
                            @if($suggestion->warnings)<p class="text-danger mb-0"><strong>Atenção:</strong> {{ implode(' · ', $suggestion->warnings) }}</p>@endif
                            <small>Revise a nota e o feedback antes de publicar.</small>
                        </section>
                    @elseif($run && in_array($run->status, [App\Domain\Grading\Enums\GradingRunStatus::Pending, App\Domain\Grading\Enums\GradingRunStatus::Processing, App\Domain\Grading\Enums\GradingRunStatus::RetryableFailed], true))
                        <div class="ai-inline-loading"><span class="ai-spinner ai-spinner-sm" aria-hidden="true"></span><span>A IA está analisando esta resposta. Você pode corrigi-la manualmente enquanto aguarda.</span></div>
                    @elseif($run?->status === App\Domain\Grading\Enums\GradingRunStatus::PermanentlyFailed)
                        <div class="ai-error-panel mb-3" role="alert"><span aria-hidden="true">!</span><div><strong>A IA não conseguiu corrigir esta questão</strong><p>{{ $run->error_message ?: 'O pedido foi encerrado. Tente novamente ou conclua a correção manualmente.' }}</p><p class="mb-0">@if($run->retryAvailableAt()->isFuture()) Nova tentativa disponível em {{ $run->retryAvailableAt()->format('d/m/Y H:i') }}. @else Você já pode solicitar uma nova tentativa. @endif</p></div></div>
                    @else
                        <div class="manual-hint"><span aria-hidden="true">✎</span>Preencha a nota e o feedback ou solicite uma sugestão da IA acima.</div>
                    @endif

                    @if($answer->gradingRuns->isNotEmpty())
                        <details class="my-3"><summary>Histórico de análises (privado)</summary>
                            @foreach($answer->gradingRuns->sortByDesc('id') as $historyRun)
                                <section class="border rounded p-2 my-2">
                                    <strong>{{ $historyRun->created_at->format('d/m/Y H:i') }} · <x-workflow-status :value="$historyRun->status->value" /></strong>
                                    <p>{{ App\Infrastructure\AI\GradingProfiles::PROFILES[$historyRun->request_snapshot['intelligenceProfile'] ?? 'balanced'] }} · {{ App\Infrastructure\AI\GradingProfiles::DETAILS[$historyRun->request_snapshot['feedbackDetail'] ?? 'medium'] }}</p>
                                    @if($historyRun->suggestion)<p>Nota sugerida: {{ $historyRun->suggestion->score }}</p><p style="white-space: pre-wrap">{{ $historyRun->suggestion->teacher_feedback ?? 'Análise privada ausente nesta sugestão antiga.' }}</p>@endif
                                    @foreach($historyRun->attemptRecords as $attempt)
                                        <p class="small mb-1">{{ $attempt->provider }} / {{ $attempt->model }} · {{ $attempt->status }} · {{ $attempt->reason }} · {{ $attempt->duration_ms ?? '—' }} ms · tokens: {{ $attempt->input_tokens ?? 'desconhecidos' }}/{{ $attempt->output_tokens ?? 'desconhecidos' }}</p>
                                    @endforeach
                                </section>
                            @endforeach
                        </details>
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

        @if($submission->status === App\Domain\Submissions\Enums\SubmissionStatus::Released)
            <div class="grading-save-bar"><div><strong>Resultado publicado</strong><small>Edite a nota ou o feedback e republique para atualizar o resultado do aluno.</small></div><button class="btn btn-success" type="submit">Salvar e republicar</button></div>
        @else
            <div class="grading-save-bar"><div><strong>Finalize sua revisão</strong><small>Confira as notas e o feedback. Esta ação publica o resultado para o aluno.</small></div><button class="btn btn-success" type="submit">Revisar e publicar</button></div>
        @endif
    </form>

    @foreach($submission->answers as $answer)
        @if($answer->activityQuestion->type === App\Domain\QuestionBank\Enums\QuestionType::Essay)
            <form id="ai-answer-{{ $answer->id }}" method="post" action="{{ route('teacher.grading.ai-answer', [$submission, $answer]) }}" data-ai-submit data-ai-label="Analisando questão...">@csrf</form>
        @endif
    @endforeach

    <div class="d-flex gap-2 mt-4">
        @if($submission->status !== App\Domain\Submissions\Enums\SubmissionStatus::Released)
            <form method="post" action="{{ route('teacher.grading.reopen', $submission) }}">@csrf<button class="btn btn-outline-secondary" type="submit">Reabrir entrega</button></form>
        @endif
    </div>
</div>
@endsection
