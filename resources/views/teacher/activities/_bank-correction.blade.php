@if($bankType === 'essay')
<label class="d-block mb-3">Correção da questão {{ $bankId }}
    <select class="form-select" name="bank_correction[{{ $bankId }}]">
        <option value="1" @selected((int) old("bank_correction.$bankId", request("bank_correction.$bankId", (int) $importCorrection)) === 1)>Importar resposta esperada, critérios e orientações da IA</option>
        <option value="0" @selected((int) old("bank_correction.$bankId", request("bank_correction.$bankId", (int) $importCorrection)) === 0)>Usar avaliação geral, sem importar especificações</option>
    </select>
</label>
@endif
