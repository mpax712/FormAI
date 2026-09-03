<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <title>@yield('title', 'FormAI')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="{{ asset('css/formai.css') }}?v={{ filemtime(public_path('css/formai.css')) }}" rel="stylesheet">
</head>
<body class="@yield('body-class', 'app-page')">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header">
    <nav class="navbar navbar-expand-lg" aria-label="Navegação principal">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}" aria-label="FormAI — página inicial">
                <span class="brand-mark" aria-hidden="true"><svg width="25" height="25" viewBox="0 0 32 32"><path d="M9 8.5h14M9 15.5h10M9 22.5h6"/><path d="m21 20 1.2 2.8L25 24l-2.8 1.2L21 28l-1.2-2.8L17 24l2.8-1.2L21 20Z"/></svg></span>
                <span class="brand-copy d-flex flex-column"><span>Form<strong>AI</strong></span><small class="d-block">Assistente do professor</small></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Abrir menu"><span class="navbar-toggler-icon"></span><span>Menu</span></button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav ms-auto align-items-lg-center nav-menu">
                    @auth
                        <li class="nav-item"><a class="nav-link nav-menu-link @if(request()->routeIs('dashboard')) active @endif" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 11 12 4l8 7v9h-5v-6H9v6H4Z"/></svg><span>Início</span></a></li>
                        @if(auth()->user()->isTeacher())
                            <li class="nav-item"><a class="nav-link nav-menu-link @if(request()->routeIs('teacher.classrooms.*')) active @endif" href="{{ route('teacher.classrooms.index') }}"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 20v-2a4 4 0 0 0-3-3.87M16 2.13a4 4 0 0 1 0 7.75"/></svg><span>Turmas</span></a></li>
                            <li class="nav-item"><a class="nav-link nav-menu-link @if(request()->routeIs('teacher.questions.*')) active @endif" href="{{ route('teacher.questions.index') }}"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2ZM8 8h8M8 12h8M8 16h5"/></svg><span>Questões</span></a></li>
                            <li class="nav-item"><a class="nav-link nav-menu-link @if(request()->routeIs('teacher.activities.*')) active @endif" href="{{ route('teacher.activities.index') }}"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span>Atividades</span></a></li>
                        @elseif(auth()->user()->isStudent())
                            <li class="nav-item"><a class="nav-link nav-menu-link @if(request()->routeIs('student.*')) active @endif" href="{{ route('student.activities.index') }}"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span>Minhas atividades</span></a></li>
                        @elseif(auth()->user()->isAdmin())
                            <li class="nav-item"><a class="nav-link nav-menu-link @if(request()->routeIs('admin.*')) active @endif" href="{{ route('admin.dashboard') }}"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.55V21h-4v-.08A1.7 1.7 0 0 0 8.95 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.58 15 1.7 1.7 0 0 0 3 14H3v-4h.08A1.7 1.7 0 0 0 4.6 8.95a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 8.97 4.6H9A1.7 1.7 0 0 0 10 3.08V3h4v.08a1.7 1.7 0 0 0 1.03 1.55 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9v.03A1.7 1.7 0 0 0 20.92 10H21v4h-.08A1.7 1.7 0 0 0 19.4 15Z"/></svg><span>Administração</span></a></li>
                        @endif
                        <li class="nav-item"><a class="nav-user" href="{{ route('profile.edit') }}" aria-label="Abrir meu perfil">@if(auth()->user()->avatarUrl())<img class="user-avatar" src="{{ auth()->user()->avatarUrl() }}" alt="">@else<span class="user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>@endif<span class="nav-user-copy"><small>Meu perfil</small><strong>{{ auth()->user()->name }}</strong></span></a></li>
                        <li class="nav-item"><form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-nav logout-button" type="submit"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M10 17l5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/></svg><span>Sair</span></button></form></li>
                    @else
                        <li class="nav-item"><a class="nav-link nav-menu-link" href="{{ route('class-code.create') }}"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 4h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3ZM9 9h.01M15 9h.01M9 15h.01M15 15h.01"/></svg><span>Acesso por código</span></a></li>
                        <li class="nav-item"><a class="nav-link nav-menu-link" href="{{ route('home') }}#como-funciona"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 4.8 1c0 1.7-2.3 2-2.3 3.5M12 17h.01"/></svg><span>Como funciona</span></a></li>
                        <li class="nav-item guest-action"><a class="btn btn-outline-primary header-button" href="{{ route('login') }}">Entrar</a></li>
                        <li class="nav-item guest-action"><a class="btn btn-primary header-button" href="{{ route('register') }}">Criar conta</a></li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>
</header>
<main id="conteudo" class="@yield('main-class', 'container page-content')">
    @if(session('status'))<div class="alert alert-success app-alert" role="status"><span aria-hidden="true">✓</span><div>{{ session('status') }}</div></div>@endif
    @if($errors->any())<div class="alert alert-danger app-alert" role="alert"><span aria-hidden="true">!</span><div><strong>Revise os dados:</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
    @yield('content')
</main>
<footer class="site-footer"><div class="container"><a class="footer-brand" href="{{ route('home') }}">Form<strong>AI</strong></a><p>Correção assistida. Decisão humana.</p><span>Feito para uma educação mais cuidadosa.</span></div></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script type="module" src="{{ asset('js/formai.js') }}?v={{ filemtime(public_path('js/formai.js')) }}"></script>
</body>
</html>
