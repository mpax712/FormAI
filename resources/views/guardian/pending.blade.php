@extends('layouts.app')
@section('title', 'Aguardando autorização · FormAI')
@section('content')
<section class="card p-4 mx-auto legal-document"><h1>Aguardando autorização do responsável</h1>
    <p>A conta do aluno permanece bloqueada até o responsável abrir o link enviado por e-mail e autorizar o acesso. Depois disso, o aluno poderá confirmar seu próprio e-mail e entrar.</p>
    <p>O link vale por sete dias. Se ele expirar ou não chegar, solicite outro:</p>
    <form method="post" action="{{ route('guardian.resend') }}">@csrf<label class="form-label" for="student-email">E-mail do aluno</label><input class="form-control mb-3" id="student-email" name="email" type="email" required><button class="btn btn-outline-primary" type="submit">Solicitar novo link</button></form>
</section>
@endsection
