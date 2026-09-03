@extends('layouts.app')
@section('title', 'Acesso por código · FormAI')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card p-4 p-md-5">
            <div class="access-code-icon" aria-hidden="true">#</div>
            <h1 class="h2 mt-3">Entrar em uma turma</h1>
            <p class="text-secondary">Digite o código informado pelo seu professor. Na próxima etapa você criará sua conta de aluno.</p>
            <form method="post" action="{{ route('class-code.lookup') }}">
                @csrf
                <label class="form-label fw-semibold" for="code">Código da turma</label>
                <input class="form-control form-control-lg classroom-code-input" id="code" name="code" value="{{ old('code') }}" maxlength="12" autocomplete="off" autocapitalize="characters" placeholder="Exemplo: AB12CD34" required autofocus>
                <div class="form-text mb-4">O código possui 8 letras e números.</div>
                <button class="btn btn-primary btn-lg w-100" type="submit">Continuar</button>
            </form>
            <p class="text-center text-secondary small mt-4 mb-0">Já possui uma conta? <a href="{{ route('login') }}">Entre com e-mail e senha</a>.</p>
        </div>
    </div>
</div>
@endsection
