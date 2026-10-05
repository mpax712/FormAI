@extends('layouts.app')
@section('title', 'Confirmar e-mail · FormAI')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card p-4 p-md-5">
            <h1 class="h3">Confirme seu e-mail</h1>
            <p>Enviamos um código de 6 números para <strong>{{ $user->email }}</strong>. Digite-o abaixo para liberar os recursos da sua conta.</p>
            <p class="small text-secondary">O código vale por {{ config('formai.email_verification_code_minutes') }} minutos. Se pedir outro, o código anterior deixa de funcionar.</p>

            <form method="post" action="{{ route('verification.code.verify') }}" class="mb-3">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="verification-code">Código de verificação</label>
                    <input class="form-control verification-code-input" id="verification-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" value="{{ old('code') }}" aria-describedby="verification-code-help" required autofocus>
                    <div class="form-text" id="verification-code-help">Digite somente os 6 números recebidos.</div>
                </div>
                <button class="btn btn-primary w-100" type="submit">Confirmar código</button>
            </form>

            <form method="post" action="{{ route('verification.send') }}">
                @csrf
                <button class="btn btn-outline-primary w-100" type="submit">Enviar um novo código</button>
            </form>
        </div>
    </div>
</div>
@endsection
