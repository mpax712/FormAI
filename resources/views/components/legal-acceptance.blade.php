@props(['id' => 'terms', 'label' => 'Li os Termos de Uso e Privacidade e aceito as regras do piloto escolar.'])
<div class="legal-summary border rounded p-3 mb-3">
    <p class="mb-2"><strong>Antes de criar a conta:</strong> o FormAI guarda dados de cadastro, atividades e resultados. A resposta pode ser enviada a um provedor de IA quando o professor pedir uma sugestão; a nota final é decidida pelo professor.</p>
    <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer">Ler Termos de Uso e Privacidade completos (abre em outra aba)</a>
</div>
<div class="form-check mb-3">
    <input class="form-check-input" id="{{ $id }}" name="terms" type="checkbox" value="1" @checked(old('terms') === '1') required>
    <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
</div>
