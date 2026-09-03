@extends('layouts.app')
@section('title', 'Cadastro de professor · FormAI')
@section('content')
<div class="row justify-content-center"><div class="col-md-8 col-lg-6"><div class="card p-4"><h1 class="h3">Criar conta de professor</h1><p class="text-secondary">Alunos entram pelo código da turma ou por um convite enviado pelo professor.</p><form method="post" action="{{ route('register') }}">@csrf
<div class="mb-3"><label class="form-label" for="name">Nome</label><input class="form-control" id="name" name="name" value="{{ old('name') }}" maxlength="120" required></div>
<div class="mb-3"><label class="form-label" for="email">E-mail</label><input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" required></div>
<div class="position-absolute start-100 overflow-hidden" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
<div class="row"><div class="col-md-6 mb-3"><label class="form-label" for="password">Senha</label><input class="form-control" id="password" name="password" type="password" minlength="{{ config('formai.password_min_length') }}" autocomplete="new-password" required><div class="form-text">Use pelo menos {{ config('formai.password_min_length') }} caracteres. Não exigimos símbolos, números ou maiúsculas.</div></div><div class="col-md-6 mb-3"><label class="form-label" for="password_confirmation">Confirmar senha</label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" minlength="{{ config('formai.password_min_length') }}" autocomplete="new-password" required></div></div>
<div class="form-check mb-3"><input class="form-check-input" id="terms" name="terms" type="checkbox" value="1" required><label class="form-check-label" for="terms">Confirmo ter 18 anos ou mais e aceito o uso de dados descrito para o piloto.</label></div>
<button class="btn btn-primary w-100" type="submit">Criar conta</button></form></div></div></div>
@endsection
