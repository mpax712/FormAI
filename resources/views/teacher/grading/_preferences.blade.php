@php($preferenceActivity = $preferenceActivity ?? $activity ?? ($submission->activity ?? null))
<fieldset data-tour="grading-preferences" class="ai-preferences border rounded p-3 my-3">
    <legend class="float-none w-auto fs-6 px-2">Configuração da análise</legend>
    <div class="v2-preference-grid">
        <label class="v2-preference-label">Análise privada para o professor
            <select class="form-select" name="feedback_detail" data-tour="feedback-detail" @if(isset($preferenceForm)) form="{{ $preferenceForm }}" @endif>
                @foreach(App\Infrastructure\AI\GradingProfiles::DETAILS as $value => $label)
                    <option value="{{ $value }}" @selected(old('feedback_detail', $preferenceActivity->feedback_detail ?? 'medium') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="v2-preference-label">Perfil de correção
            <select class="form-select" name="intelligence_profile" data-tour="grading-profile" @if(isset($preferenceForm)) form="{{ $preferenceForm }}" @endif>
                @foreach(App\Infrastructure\AI\GradingProfiles::PROFILES as $value => $label)
                    <option value="{{ $value }}" @selected(old('intelligence_profile', $preferenceActivity->intelligence_profile ?? 'balanced') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="v2-preference-label">Rigidez da correção
            <select class="form-select" name="grading_strictness" data-tour="grading-strictness" @if(isset($preferenceForm)) form="{{ $preferenceForm }}" @endif>
                @foreach(App\Infrastructure\AI\GradingProfiles::STRICTNESS as $value => $label)
                    <option value="{{ $value }}" @selected(old('grading_strictness', $preferenceActivity->grading_strictness ?? 'balanced') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>
    <p class="small text-secondary mt-2 mb-0">Perfil de correção seleciona os modelos e o orçamento da análise, não muda sozinho a nota: Econômico prioriza custo e agilidade; Equilibrado combina custo e profundidade; Avançado permite maior orçamento e pode demorar mais. A disponibilidade depende da configuração administrativa.</p>
    <p class="small text-secondary mt-2 mb-0">Análise privada curta: até 30 palavras. Média: até 150. Detalhada: até 350. Esse texto fica visível somente ao professor.</p>
    <p class="small text-secondary mt-2 mb-0">Rigidez: Flexível valoriza compreensão e crédito parcial; Equilibrada pondera acertos e lacunas; Rigorosa exige evidências claras em cada critério. A rubrica e a pontuação máxima continuam valendo em todos os níveis.</p>
    <p class="small mb-0">O feedback ao aluno permanece separado e depende da sua revisão. Uma nova tentativa para a mesma questão fica disponível após {{ (int) ceil(config('ai_grading.retry_cooldown_seconds', 300) / 60) }} minutos; ela substitui solicitações anteriores que falharam.</p>
</fieldset>
