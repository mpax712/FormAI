@php
    $type = $question['type'] ?? 'essay';
    $options = array_values($question['options'] ?? []);
    $correctOption = isset($question['correct_option'])
        ? (int) $question['correct_option']
        : collect($options)->search(fn ($option) => (bool) ($option['is_correct'] ?? false));
    if ($correctOption === false) $correctOption = 0;
    $correctOptions = isset($question['correct_options'])
        ? array_map('intval', $question['correct_options'])
        : array_keys(array_filter($options, fn ($option) => (bool) ($option['is_correct'] ?? false)));
    while (count($options) < 4) $options[] = [];
    $rubric = array_values($question['rubric'] ?? []);
    $hasCriteria = collect($rubric)->contains(fn ($item) => filled($item['label'] ?? null) || filled($item['description'] ?? null) || filled($item['weight'] ?? null));
    while (count($rubric) < 3) $rubric[] = [];
@endphp

<article class="activity-question-builder activity-question-glass" data-question-card>
    <div class="question-builder-heading">
        <div><span class="question-number" data-question-number>{{ is_numeric($index) ? $index + 1 : '' }}</span><div><strong data-question-kind-label>{{ match($type) { 'context' => 'Bloco de contexto', 'multiple_choice' => 'Múltipla escolha', 'single_choice' => 'Escolha única', default => 'Questão dissertativa' } }}</strong><small>{{ $type === 'context' ? 'Texto de apoio, sem resposta ou pontos' : 'Criada somente para esta atividade' }}</small></div></div>
        <button class="btn btn-sm btn-outline-danger" type="button" data-remove-question>Remover</button>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <label class="form-label" for="question-{{ $index }}-body">{{ $type === 'context' ? 'Texto de apoio' : 'Enunciado' }}</label>
            <textarea class="form-control" id="question-{{ $index }}-body" name="questions[{{ $index }}][body]" rows="4" maxlength="10000" placeholder="Escreva em parágrafos. As quebras de linha aparecerão para o aluno." required>{{ $question['body'] ?? '' }}</textarea>
        </div>
        <div class="col-sm-7 col-lg-2">
            <label class="form-label" for="question-{{ $index }}-type">Tipo</label>
            <select class="form-select" id="question-{{ $index }}-type" name="questions[{{ $index }}][type]" data-question-type required>
                <option value="essay" @selected($type === 'essay')>Dissertativa</option>
                <option value="single_choice" @selected($type === 'single_choice')>Escolha única</option>
                <option value="multiple_choice" @selected($type === 'multiple_choice')>Múltipla escolha</option>
                <option value="context" @selected($type === 'context')>Bloco de contexto</option>
            </select>
        </div>
        <div class="col-sm-5 col-lg-2" data-score-field @if($type === 'context') hidden @endif>
            <label class="form-label" for="question-{{ $index }}-score">Pontos</label>
            <input class="form-control" id="question-{{ $index }}-score" name="questions[{{ $index }}][max_score]" type="number" min="0.01" max="1000" step="0.01" value="{{ $question['max_score'] ?? 1 }}" required>
        </div>
    </div>

    <p class="form-text mt-2 mb-0" data-context-hint @if($type !== 'context') hidden @endif>Este bloco apresenta leitura ou instruções antes das questões seguintes. Não recebe resposta nem pontuação.</p>

    <div class="question-kind-panel mt-3" data-essay-fields @if($type !== 'essay') hidden @endif>
        <div class="mb-3">
            <label class="form-label" for="question-{{ $index }}-expected">Resposta esperada <span class="text-secondary fw-normal">(opcional)</span></label>
            <textarea class="form-control" id="question-{{ $index }}-expected" name="questions[{{ $index }}][expected_answer]" rows="3" maxlength="10000" placeholder="Descreva os pontos que uma boa resposta deve apresentar.">{{ $question['expected_answer'] ?? '' }}</textarea>
        </div>
        <label class="criteria-switch"><input type="checkbox" role="switch" data-criteria-toggle aria-controls="question-{{ $index }}-criteria" @checked($hasCriteria)><span>Personalizar critérios de correção</span></label>
        <fieldset id="question-{{ $index }}-criteria" data-criteria-panel @if(! $hasCriteria) hidden disabled @endif>
            <div class="mb-2"><strong>Critérios de correção</strong> <span class="text-secondary">(opcional)</span></div>
            <p class="form-text mt-0">Preencha os critérios desejados. Quando usados, a soma dos pontos deve ser igual à pontuação da questão.</p>
            @foreach($rubric as $criterionIndex => $criterion)
                <div class="row g-2 mb-2">
                    <div class="col-md-3">
                        <label class="visually-hidden" for="question-{{ $index }}-criterion-{{ $criterionIndex }}-label">Nome do critério</label>
                        <input class="form-control" id="question-{{ $index }}-criterion-{{ $criterionIndex }}-label" name="questions[{{ $index }}][rubric][{{ $criterionIndex }}][label]" maxlength="120" placeholder="Ex.: Clareza" value="{{ $criterion['label'] ?? '' }}">
                    </div>
                    <div class="col-md-7">
                        <label class="visually-hidden" for="question-{{ $index }}-criterion-{{ $criterionIndex }}-description">Descrição do critério</label>
                        <input class="form-control" id="question-{{ $index }}-criterion-{{ $criterionIndex }}-description" name="questions[{{ $index }}][rubric][{{ $criterionIndex }}][description]" maxlength="3000" placeholder="O que deve ser avaliado" value="{{ $criterion['description'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="visually-hidden" for="question-{{ $index }}-criterion-{{ $criterionIndex }}-weight">Pontos</label>
                        <input class="form-control" id="question-{{ $index }}-criterion-{{ $criterionIndex }}-weight" name="questions[{{ $index }}][rubric][{{ $criterionIndex }}][weight]" type="number" min="0.01" max="1000" step="0.01" placeholder="Pontos" value="{{ $criterion['weight'] ?? '' }}" data-rubric-points>
                    </div>
                </div>
            @endforeach
            <div class="form-text" data-rubric-summary aria-live="polite"></div>
        </fieldset>
    </div>

    <div class="question-kind-panel mt-3" data-choice-fields @if(! in_array($type, ['single_choice', 'multiple_choice'], true)) hidden @endif>
        <label class="form-label">Alternativas e gabarito</label>
        @foreach($options as $optionIndex => $option)
            <div class="input-group mb-2 question-option-editor">
                <div class="input-group-text" data-single-correct @if($type !== 'single_choice') hidden @endif><input class="form-check-input mt-0" type="radio" name="questions[{{ $index }}][correct_option]" value="{{ $optionIndex }}" aria-label="Marcar alternativa {{ chr(65 + $optionIndex) }} como a única correta" @checked($correctOption === $optionIndex)></div>
                <div class="input-group-text" data-multiple-correct @if($type !== 'multiple_choice') hidden @endif><input class="form-check-input mt-0" type="checkbox" name="questions[{{ $index }}][correct_options][]" value="{{ $optionIndex }}" aria-label="Marcar alternativa {{ chr(65 + $optionIndex) }} como correta" @checked(in_array($optionIndex, $correctOptions, true))></div>
                <input type="hidden" name="questions[{{ $index }}][options][{{ $optionIndex }}][is_correct]" value="0" data-correct-hidden>
                <input class="form-control" name="questions[{{ $index }}][options][{{ $optionIndex }}][text]" aria-label="Texto da alternativa {{ chr(65 + $optionIndex) }}" placeholder="Alternativa {{ chr(65 + $optionIndex) }}" value="{{ $option['text'] ?? '' }}">
            </div>
        @endforeach
        <div class="form-text" data-choice-help>{{ $type === 'multiple_choice' ? 'Preencha ao menos duas alternativas e marque todas as corretas (pelo menos duas). A pontuação é integral somente com a seleção exata.' : 'Preencha ao menos duas alternativas e marque uma correta.' }}</div>
    </div>

</article>
