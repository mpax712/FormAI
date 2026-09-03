@extends('layouts.app')
@section('title', 'Verificacao administrativa · FormAI')
@section('content')
<div class="row justify-content-center"><div class="col-md-6"><div class="card p-4"><h1 class="h3">Segunda verificacao</h1><p>Digite o codigo de seis digitos enviado por e-mail.</p><form method="post" action="{{ route('mfa.verify') }}">@csrf<label class="form-label" for="code">Codigo</label><input class="form-control form-control-lg mb-3" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus><button class="btn btn-primary" type="submit">Confirmar</button></form></div></div></div>
@endsection
