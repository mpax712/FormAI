@extends('layouts.app')
@section('title', 'Verificar e-mail · FormAI')
@section('content')
<div class="row justify-content-center"><div class="col-md-7"><div class="card p-4"><h1 class="h3">Verifique seu e-mail</h1><p>Enviamos um link para confirmar sua identidade. Essa etapa e obrigatoria antes de criar turmas ou usar IA.</p><form method="post" action="{{ route('verification.send') }}">@csrf<button class="btn btn-primary" type="submit">Reenviar link</button></form></div></div></div>
@endsection
