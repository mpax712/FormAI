@extends('layouts.app')
@section('title', 'Recuperar senha · FormAI')
@section('content')
<div class="row justify-content-center"><div class="col-md-6"><div class="card p-4"><h1 class="h3">Recuperar senha</h1><p>Informe seu e-mail. A resposta sera a mesma mesmo que a conta nao exista.</p><form method="post" action="{{ route('password.email') }}">@csrf<label class="form-label" for="email">E-mail</label><input class="form-control mb-3" id="email" name="email" type="email" required><button class="btn btn-primary" type="submit">Enviar link</button></form></div></div></div>
@endsection
