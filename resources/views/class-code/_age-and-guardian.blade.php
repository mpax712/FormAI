<div class="mb-3">
    <label class="form-label" for="age-band">Faixa etária</label>
    <select class="form-select" id="age-band" name="age_band" data-age-band required>
        <option value="">Selecione sua faixa etária</option>
        <option value="under_13" @selected(old('age_band') === 'under_13')>Menos de 13 anos</option>
        <option value="13_17" @selected(old('age_band') === '13_17')>De 13 a 17 anos</option>
        <option value="adult" @selected(old('age_band') === 'adult')>18 anos ou mais</option>
    </select>
</div>
<div class="mb-3" data-guardian-field @if(old('age_band') !== 'under_13') hidden @endif>
    <label class="form-label" for="guardian-email">E-mail do responsável</label>
    <input class="form-control" id="guardian-email" name="guardian_email" type="email" value="{{ old('guardian_email') }}" autocomplete="email" @if(old('age_band') === 'under_13') required @endif>
    <div class="form-text">Se você tem menos de 13 anos, enviaremos um link para o responsável autorizar seu acesso. Sua conta ficará aguardando essa confirmação.</div>
</div>
