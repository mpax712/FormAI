@extends('layouts.app')
@section('title', 'Autorizar acesso · FormAI')
@section('content')
<section class="card p-4 mx-auto legal-document" aria-labelledby="guardian-title">
    <h1 id="guardian-title">Autorização do responsável</h1>
    @if(! $authorization || $authorization->expires_at->isPast() || $authorization->accepted_at || $authorization->declined_at || $authorization->terms_version !== config('legal.terms_version'))
        <p>Este link não está mais disponível. Se a solicitação ainda estiver pendente, peça um novo link usando o e-mail do aluno.</p>
        <form method="post" action="{{ route('guardian.resend') }}">@csrf<label class="form-label" for="student-email">E-mail do aluno</label><input class="form-control mb-3" id="student-email" name="email" type="email" required><button class="btn btn-outline-primary" type="submit">Solicitar novo link</button></form>
    @else
        <p>Foi solicitada uma conta para <strong>{{ $authorization->user->name }}</strong>. O acesso à escola permanece bloqueado até sua decisão.</p>
        <p>Leia os <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer">Termos de Uso e Privacidade (abre em outra aba)</a>. As respostas do aluno podem ser enviadas ao provedor de IA quando o professor solicitar uma sugestão de correção.</p>
        <form method="post" action="{{ route('guardian.decide', $token) }}">@csrf
            <div class="mb-3"><label class="form-label" for="guardian-name">Seu nome</label><input class="form-control" id="guardian-name" name="guardian_name" maxlength="120" required></div>
            <div class="form-check mb-3"><input class="form-check-input" id="relationship" name="relationship" type="checkbox" value="1" required><label class="form-check-label" for="relationship">Sou responsável legal por este aluno.</label></div>
            <div class="form-check mb-4"><input class="form-check-input" id="guardian-terms" name="terms" type="checkbox" value="1" required><label class="form-check-label" for="guardian-terms">Li os termos e autorizo o acesso deste aluno ao FormAI.</label></div>
            <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit" name="decision" value="approve">Autorizar acesso</button><button class="btn btn-outline-danger" type="submit" name="decision" value="decline" formnovalidate>Não autorizar</button></div>
        </form>
    @endif
</section>
@endsection
