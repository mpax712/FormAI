@extends('layouts.app')
@section('title', 'Cadastro de aluno · FormAI')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-9 col-lg-7">
        <div class="card p-4 p-md-5">
            <span class="badge text-bg-light border align-self-start mb-3">Turma encontrada</span>
            <h1 class="h2">{{ $classroom->name }}</h1>
            <p class="text-secondary">Crie sua conta. O professor receberá sua solicitação e precisará aprovar sua entrada.</p>
            <form method="post" action="{{ route('class-code.store') }}">
                @csrf
                <div class="mb-3"><label class="form-label" for="student-name">Nome completo</label><input class="form-control" id="student-name" name="name" value="{{ old('name') }}" maxlength="120" autocomplete="name" required></div>
                <div class="mb-3"><label class="form-label" for="student-email">E-mail</label><input class="form-control" id="student-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></div>
                <div class="position-absolute start-100 overflow-hidden" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                <div class="row"><div class="col-md-6 mb-3"><label class="form-label" for="student-password">Senha</label><input class="form-control" id="student-password" name="password" type="password" minlength="{{ config('formai.password_min_length') }}" autocomplete="new-password" required><div class="form-text">Mínimo de {{ config('formai.password_min_length') }} caracteres.</div></div><div class="col-md-6 mb-3"><label class="form-label" for="student-password-confirmation">Confirmar senha</label><input class="form-control" id="student-password-confirmation" name="password_confirmation" type="password" minlength="{{ config('formai.password_min_length') }}" autocomplete="new-password" required></div></div>
                <div class="form-check mb-4"><input class="form-check-input" id="student-terms" name="terms" type="checkbox" value="1" required><label class="form-check-label" for="student-terms">Aceito os termos de uso e o tratamento dos dados necessários para participar da turma.</label></div>
                <button class="btn btn-primary btn-lg w-100" type="submit">Criar conta e solicitar entrada</button>
            </form>
        </div>
    </div>
</div>
@endsection
