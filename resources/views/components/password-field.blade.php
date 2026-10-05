@props(['id', 'name' => 'password', 'label' => 'Senha', 'autocomplete' => 'new-password', 'minlength' => null, 'help' => null])
<div class="mb-3">
    <label class="form-label" for="{{ $id }}">{{ $label }}</label>
    <div class="input-group password-field">
        <input class="form-control" id="{{ $id }}" name="{{ $name }}" type="password" autocomplete="{{ $autocomplete }}" @if($minlength) minlength="{{ $minlength }}" @endif required>
        <button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle aria-controls="{{ $id }}" aria-label="Mostrar {{ mb_strtolower($label) }}" aria-pressed="false">Mostrar</button>
    </div>
    @if($help)<div class="form-text">{{ $help }}</div>@endif
</div>
