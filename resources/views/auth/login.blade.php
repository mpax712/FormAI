@extends('layouts.app')
@section('title', 'Entrar · FormAI')
@section('content')
<div class="row justify-content-center"><div class="col-md-7 col-lg-5"><div class="card p-4"><h1 class="h3">Entrar</h1><form method="post" action="{{ route('login') }}">@csrf
<div class="mb-3"><label class="form-label" for="email">E-mail</label><input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus></div>
<div class="mb-3"><label class="form-label" for="password">Senha</label><input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required></div>
<div class="form-check mb-3"><input class="form-check-input" id="remember" name="remember" type="checkbox" value="1"><label class="form-check-label" for="remember">Manter sessao</label></div>
<button class="btn btn-primary w-100" type="submit">Entrar</button></form><a class="mt-3" href="{{ route('password.request') }}">Esqueci minha senha</a></div></div></div>
@endsection
