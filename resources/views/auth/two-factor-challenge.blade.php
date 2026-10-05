@extends('layouts.guest')
@section('title', 'Verificação em duas etapas')
@section('content')
<section class="guest-card text-center"><h1>Verificação em duas etapas</h1><p>Digite o código de seis dígitos do seu aplicativo autenticador.</p>
    <form method="POST" action="{{ route('two-factor.login') }}">@csrf<label class="form-label" for="code">Código de verificação</label><input id="code" autocomplete="one-time-code" required class="form-control form-control-lg text-center mb-3" name="code" inputmode="numeric" maxlength="6" placeholder="000 000" autofocus><button class="btn btn-primary w-100 py-2">Verificar código</button></form>
    <details class="mt-3 text-start"><summary class="text-center">Usar um código de recuperação</summary><form class="mt-3" method="POST" action="{{ route('two-factor.login') }}">@csrf<label class="form-label" for="recovery_code">Código de recuperação</label><input class="form-control mb-3" id="recovery_code" name="recovery_code" autocomplete="one-time-code" required><button class="btn btn-outline-primary w-100">Entrar com código de recuperação</button></form></details>
</section>
@endsection
