@extends('layouts.app')
@section('title', 'Autorização · FormAI')
@section('content')
<section class="card p-4 mx-auto legal-document"><h1>Autorização do responsável</h1>
    @if($status === 'approved')<p>A autorização foi registrada. O aluno já pode verificar seu e-mail e entrar na conta. Se entrou por código, a entrada na turma dependerá da configuração escolhida pelo professor no momento do cadastro.</p>
    @elseif($status === 'declined')<p>Você não autorizou o acesso. A conta do aluno continuará bloqueada. Para dúvidas, escreva para <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>
    @else<p>Este link expirou ou já foi usado. Solicite um novo link pelo cadastro do aluno ou entre em contato com <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>@endif
</section>
@endsection
