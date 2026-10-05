@extends('layouts.app')
@section('title', 'Cadastro de professor · FormAI')
@section('content')
<div class="row justify-content-center"><div class="col-md-8 col-lg-6"><div class="card p-4"><h1 class="h3">Criar conta de professor</h1><p class="text-secondary">Alunos entram pelo código da turma ou por um convite enviado pelo professor.</p><form method="post" action="{{ route('register') }}">@csrf
<div class="mb-3"><label class="form-label" for="name">Nome</label><input class="form-control" id="name" name="name" value="{{ old('name') }}" maxlength="120" required></div>
<div class="mb-3"><label class="form-label" for="email">E-mail</label><input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" required></div>
<div class="position-absolute start-100 overflow-hidden" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
<div class="row"><div class="col-md-6"><x-password-field id="password" :minlength="config('formai.password_min_length')" :help="'Use pelo menos '.config('formai.password_min_length').' caracteres. Não exigimos símbolos, números ou maiúsculas.'" /></div><div class="col-md-6"><x-password-field id="password_confirmation" name="password_confirmation" label="Confirmar senha" :minlength="config('formai.password_min_length')" /></div></div>
<p class="small text-secondary">O cadastro de professor é destinado a pessoas com 18 anos ou mais.</p>
<x-legal-acceptance label="Confirmo ter 18 anos ou mais, li os Termos de Uso e Privacidade e aceito as regras do piloto escolar." />
<button class="btn btn-primary w-100" type="submit">Criar conta</button></form></div></div></div>
@endsection
