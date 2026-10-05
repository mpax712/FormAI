@php
    $isResult = $isResult ?? false;
    $answer = $answers->get($question->id);
    $selectedKeys = array_filter(explode(',', (string) $answer?->selected_option_key), fn ($key) => $key !== '');
    $isEssay = $question->type === App\Domain\QuestionBank\Enums\QuestionType::Essay;
    $isMultiple = $question->type === App\Domain\QuestionBank\Enums\QuestionType::MultipleChoice;
    $correctKeys = collect($question->options_snapshot ?? [])->filter(fn ($option) => (bool) ($option['is_correct'] ?? false))->pluck('key')->all();
    $correct = ! $isEssay && count($selectedKeys) === count($correctKeys) && empty(array_diff($selectedKeys, $correctKeys));
@endphp
@if($question->type === App\Domain\QuestionBank\Enums\QuestionType::Context)
    <section class="card question-card context-block p-4 mb-4" aria-label="Texto de apoio">
        <span class="question-type-label">Texto de apoio</span>
        <div class="question-rich-text">{{ $question->body }}</div>
    </section>
@else
    @if($isResult)
        <article class="card question-card student-question-card student-result-card p-4 mb-4" id="questao-{{ $question->position }}">
    @else
        <form class="card question-card student-question-card p-4 mb-4" id="questao-{{ $question->position }}" method="post" action="{{ route('student.answers.save', [$submission, $question]) }}" data-autosave>
            @csrf @method('PUT')
            <input type="hidden" name="version" value="{{ $answer?->version ?? 0 }}">
    @endif
        <div class="student-question-heading">
            <span class="question-type-label">Questão {{ $question->position }}</span>
            <span class="question-points">{{ number_format((float) $question->max_score, 2, ',', '.') }} pontos</span>
        </div>
        <h2 class="visually-hidden">Questão {{ $question->position }}</h2>
        <div class="question-rich-text question-stem">{{ $question->body }}</div>
        @if($isEssay)
            @if($isResult)
                <strong class="form-label">Sua resposta</strong>
                <div class="question-rich-text student-essay-answer">{{ $answer?->response_text ?: 'Resposta não registrada.' }}</div>
            @else
                <label class="form-label" for="answer-{{ $question->id }}">Sua resposta</label>
                <textarea class="form-control" id="answer-{{ $question->id }}" name="response_text" rows="7" maxlength="30000">{{ $answer?->response_text }}</textarea>
            @endif
        @else
            <fieldset class="student-options" @if($isResult) disabled @endif>
                <legend class="form-label">{{ $isMultiple ? 'Marque todas as alternativas corretas' : 'Marque uma alternativa' }}</legend>
                @foreach($question->options_snapshot ?? [] as $option)
                    @php($selected = in_array($option['key'], $selectedKeys, true))
                    @php($optionCorrect = in_array($option['key'], $correctKeys, true))
                    <label class="student-option @if($isResult && $optionCorrect) is-correct @endif @if($isResult && $selected && ! $optionCorrect) is-incorrect @endif" for="q{{ $question->id }}-{{ $option['key'] }}">
                        <input id="q{{ $question->id }}-{{ $option['key'] }}" name="{{ $isMultiple ? 'selected_option_keys[]' : 'selected_option_key' }}" type="{{ $isMultiple ? 'checkbox' : 'radio' }}" value="{{ $option['key'] }}" @checked($selected)>
                        <span class="student-option-key">{{ $option['key'] }}</span>
                        <span class="question-rich-text">{{ $option['text'] }}</span>
                        @if($isResult && ($selected || $optionCorrect))
                            <span class="student-option-mark">
                                @if($selected && $optionCorrect) Sua escolha · correta
                                @elseif($selected) Sua escolha · incorreta
                                @elseif($optionCorrect) Resposta correta
                                @endif
                            </span>
                        @endif
                    </label>
                @endforeach
            </fieldset>
        @endif
        @if($isResult)
            <div class="student-question-feedback @if(! $isEssay) {{ $correct ? 'is-success' : 'is-error' }} @endif">
                <strong>{{ $isEssay ? 'Correção do professor' : ($correct ? 'Você acertou' : 'Você errou') }}</strong>
                @if(! $isEssay && ! $correct && (float) ($answer?->gradingDecision?->score ?? 0) > 0)
                    <small>Nota ajustada pelo professor</small>
                @endif
                <span>
                    @if($isEssay && ! $answer?->gradingDecision)
                        Nota da questão indisponível
                    @else
                        {{ number_format((float) ($answer?->gradingDecision?->score ?? ($correct ? $question->max_score : 0)), 2, ',', '.') }} / {{ number_format((float) $question->max_score, 2, ',', '.') }} pontos
                    @endif
                </span>
                @if($answer?->gradingDecision?->feedback)
                    <p class="question-rich-text mb-0">{{ $answer->gradingDecision->feedback }}</p>
                @endif
            </div>
        @else
            <div class="autosave-feedback mt-3">
                <div class="autosave-status small text-secondary" data-save-status role="status" aria-live="polite">{{ $answer ? 'Rascunho salvo' : 'Ainda não salvo' }}</div>
                <button class="btn btn-outline-primary btn-sm d-none" type="button" data-save-retry>Tentar salvar novamente</button>
            </div>
        @endif
    @if($isResult)</article>@else</form>@endif
@endif
