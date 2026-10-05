@extends('layouts.app')
@section('title', 'Cadastro de aluno · FormAI')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-9 col-lg-7">
        <div class="card p-4 p-md-5">
            <span class="badge text-bg-light border align-self-start mb-3">Turma encontrada</span>
            <h1 class="h2">{{ $classroom->name }}</h1>
            <p class="text-secondary">{{ $classroom->auto_approve_join ? 'Crie sua conta. Após verificar seu e-mail, você poderá acessar a turma sem esperar a aprovação do professor.' : 'Crie sua conta. O professor receberá sua solicitação e precisará aprovar sua entrada.' }} Menores de 13 anos precisam também da autorização do responsável.</p>
            <form method="post" action="{{ route('class-code.store') }}">
                @csrf
                <div class="mb-3"><label class="form-label" for="student-name">Nome completo</label><input class="form-control" id="student-name" name="name" value="{{ old('name') }}" maxlength="120" autocomplete="name" required></div>
                <div class="mb-3"><label class="form-label" for="student-email">E-mail</label><input class="form-control" id="student-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></div>
                <div class="position-absolute start-100 overflow-hidden" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                <div class="row"><div class="col-md-6"><x-password-field id="student-password" :minlength="config('formai.password_min_length')" :help="'Mínimo de '.config('formai.password_min_length').' caracteres.'" /></div><div class="col-md-6"><x-password-field id="student-password-confirmation" name="password_confirmation" label="Confirmar senha" :minlength="config('formai.password_min_length')" /></div></div>
                @include('class-code._age-and-guardian')
                <x-legal-acceptance id="student-terms" />
                <button class="btn btn-primary btn-lg w-100" type="submit">{{ $classroom->auto_approve_join ? 'Criar conta e entrar na turma' : 'Criar conta e solicitar entrada' }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
