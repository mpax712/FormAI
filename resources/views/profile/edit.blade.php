@extends('layouts.app')
@section('title', 'Meu perfil · FormAI')
@section('content')
<div class="profile-heading mb-4">
    <div><h1>Meu perfil</h1><p class="text-secondary mb-0">Atualize seus dados pessoais e a segurança da sua conta.</p></div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card p-4 text-center h-100">
            @if($user->avatarUrl())
                <img class="profile-avatar mx-auto" src="{{ $user->avatarUrl() }}" alt="Foto de perfil de {{ $user->name }}">
            @else
                <span class="profile-avatar profile-avatar-placeholder mx-auto" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            @endif
            <h2 class="h5 mt-3 mb-1">{{ $user->name }}</h2>
            <p class="text-secondary small">{{ match($user->role->value) { 'teacher' => 'Professor', 'student' => 'Aluno', default => 'Administrador' } }}</p>
            <form method="post" action="{{ route('profile.avatar') }}" enctype="multipart/form-data">
                @csrf
                <label class="form-label text-start w-100" for="avatar">Nova foto</label>
                <input class="form-control mb-2" id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" required>
                <div class="form-text text-start mb-3">JPG, PNG ou WebP, até 2 MB.</div>
                <button class="btn btn-primary w-100" type="submit">Salvar foto</button>
            </form>
            @if($user->avatarUrl())
                <form class="mt-2" method="post" action="{{ route('profile.avatar.destroy') }}">@csrf @method('DELETE')<button class="btn btn-outline-secondary w-100" type="submit">Remover foto</button></form>
            @endif
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card p-4 mb-4">
            <h2 class="h4">Dados pessoais</h2>
            <form method="post" action="{{ route('profile.update') }}">@csrf @method('PATCH')
                <div class="mb-3"><label class="form-label" for="profile-name">Nome</label>@if($user->isTeacher())<input class="form-control" id="profile-name" name="name" value="{{ old('name', $user->name) }}" maxlength="120" required>@else<input class="form-control" id="profile-name" value="{{ $user->name }}" disabled><div class="form-text">Por segurança acadêmica, somente contas de professor podem alterar o próprio nome.</div>@endif</div>
                <div class="mb-3"><label class="form-label" for="profile-email">E-mail</label><input class="form-control" id="profile-email" name="email" type="email" value="{{ old('email', $user->email) }}" required><div class="form-text">Ao trocar o e-mail, será necessário confirmar o novo endereço.</div></div>
                <button class="btn btn-primary" type="submit">Salvar dados</button>
            </form>
        </div>

        <div class="card p-4 mb-4">
            <h2 class="h4">Alterar senha</h2>
            <form method="post" action="{{ route('profile.password') }}">@csrf @method('PUT')
                <div class="mb-3"><label class="form-label" for="current-password">Senha atual</label><input class="form-control" id="current-password" name="current_password" type="password" autocomplete="current-password" required></div>
                <div class="row"><div class="col-md-6 mb-3"><label class="form-label" for="new-password">Nova senha</label><input class="form-control" id="new-password" name="password" type="password" minlength="{{ config('formai.password_min_length') }}" autocomplete="new-password" required></div><div class="col-md-6 mb-3"><label class="form-label" for="new-password-confirmation">Confirmar nova senha</label><input class="form-control" id="new-password-confirmation" name="password_confirmation" type="password" minlength="{{ config('formai.password_min_length') }}" autocomplete="new-password" required></div></div>
                <button class="btn btn-outline-primary" type="submit">Atualizar senha</button>
            </form>
        </div>

        @if(! $user->isAdmin())
            <div class="card border-danger p-4">
                <h2 class="h5 text-danger">Excluir conta</h2>
                <p class="small">A conta será desativada imediatamente e os dados pessoais anonimizados após 30 dias.</p>
                <form method="post" action="{{ route('account.destroy') }}" data-confirm="Tem certeza? O acesso será encerrado imediatamente.">@csrf @method('DELETE')<label class="form-label" for="delete-password">Confirme sua senha</label><div class="input-group"><input class="form-control" id="delete-password" name="password" type="password" autocomplete="current-password" required><button class="btn btn-outline-danger" type="submit">Solicitar exclusão</button></div></form>
            </div>
        @endif
    </div>
</div>
@endsection
