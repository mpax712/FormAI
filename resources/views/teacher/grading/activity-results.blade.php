@extends('layouts.app')

@section('title', 'Republicar resultados · '.$activity->title)

@section('content')
<div class="grading-page">
    <header class="grading-detail-header">
        <div>
            <a class="grading-back-link" href="{{ route('teacher.activities.show', $activity) }}">← Voltar para as entregas</a>
            <span class="grading-eyebrow">Resultados publicados</span>
            <h1>Editar resultados da atividade</h1>
            <p>{{ $activity->title }} · {{ $submissions->count() }} entrega(s) publicada(s)</p>
        </div>
    </header>

    @if($submissions->isEmpty())
        <div class="card p-4"><p class="mb-0">Ainda não há resultados publicados nesta atividade.</p></div>
    @else
        <form method="post" action="{{ route('teacher.grading.activity-results.update', $activity) }}" data-bulk-republish>
            @csrf
            @method('PUT')
            <p class="text-secondary">Altere as notas ou o feedback. As entregas editadas serão selecionadas automaticamente. Você também pode selecionar uma entrega para republicá-la sem mudar os campos.</p>
            @foreach($submissions as $submission)
                <section class="card p-4 mb-4" data-result-submission>
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h2 class="h5 mb-1">{{ $submission->student->name }}</h2>
                            <p class="text-secondary mb-0">Nota atual: {{ number_format((float) $submission->final_score, 2, ',', '.') }} / {{ number_format((float) $activity->total_score, 2, ',', '.') }}</p>
                        </div>
                        <label class="form-check-label d-flex align-items-center gap-2">
                            <input class="form-check-input mt-0" type="checkbox" name="selected[]" value="{{ $submission->public_id }}" data-result-select @checked(in_array($submission->public_id, old('selected', []), true))>
                            Incluir na republicação
                        </label>
                    </div>
                    @foreach($submission->answers as $answer)
                        @php
                            $question = $answer->activityQuestion;
                            $objective = in_array($question->type, [App\Domain\QuestionBank\Enums\QuestionType::SingleChoice, App\Domain\QuestionBank\Enums\QuestionType::MultipleChoice], true);
                            $correctKeys = collect($question->options_snapshot ?? [])->filter(fn ($option) => (bool) ($option['is_correct'] ?? false))->pluck('key')->sort()->values()->all();
                            $selectedKeys = explode(',', (string) $answer->selected_option_key);
                            sort($selectedKeys);
                            $automaticScore = $selectedKeys === $correctKeys ? $question->max_score : 0;
                            $field = 'grades.'.$submission->public_id.'.'.$answer->id;
                        @endphp
                        @if($question->type !== App\Domain\QuestionBank\Enums\QuestionType::Context)
                            <div class="border-top pt-3 mt-3">
                                <h3 class="h6 question-rich-text">Questão {{ $question->position }} · {{ $question->body }}</h3>
                                <p class="question-rich-text text-secondary">Resposta: {{ $objective ? collect($question->options_snapshot ?? [])->whereIn('key', explode(',', (string) $answer->selected_option_key))->map(fn ($option) => $option['key'].'. '.$option['text'])->implode('; ') : $answer->response_text }}</p>
                                @if($objective)<p class="small text-secondary">Gabarito: {{ implode(', ', $correctKeys) }} · Correção automática: {{ number_format((float) $automaticScore, 2, ',', '.') }} ponto(s)</p>@endif
                                <div class="row grading-fields">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label" for="batch-score-{{ $answer->id }}">Nota <small>máx. {{ $question->max_score }}</small></label>
                                        <input class="form-control" id="batch-score-{{ $answer->id }}" name="grades[{{ $submission->public_id }}][{{ $answer->id }}][score]" type="number" min="0" max="{{ $question->max_score }}" step="0.01" value="{{ old($field.'.score', $answer->gradingDecision?->score ?? ($objective ? $automaticScore : '')) }}">
                                    </div>
                                    <div class="col-md-9 mb-3">
                                        <label class="form-label" for="batch-feedback-{{ $answer->id }}">Feedback ao aluno</label>
                                        <textarea class="form-control" id="batch-feedback-{{ $answer->id }}" name="grades[{{ $submission->public_id }}][{{ $answer->id }}][feedback]" rows="2">{{ old($field.'.feedback', $answer->gradingDecision?->feedback) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </section>
            @endforeach
            <div class="grading-save-bar"><div><strong>Republicar resultados</strong><small>Somente as entregas selecionadas serão atualizadas. Os alunos verão as novas notas e feedbacks imediatamente.</small></div><button class="btn btn-success" type="submit">Republicar selecionados</button></div>
        </form>
    @endif
</div>
@endsection
