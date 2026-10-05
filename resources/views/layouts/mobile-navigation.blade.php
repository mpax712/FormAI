@php
    $mobileItems = [
        ['label' => 'Início', 'route' => auth()->user()->isAdmin() ? 'admin.dashboard' : 'dashboard', 'active' => request()->routeIs('dashboard', 'admin.dashboard'), 'icon' => 'home'],
    ];
    if (auth()->user()->isTeacher()) {
        $mobileItems[] = ['label' => 'Turmas', 'route' => 'teacher.classrooms.index', 'active' => request()->routeIs('teacher.classrooms.*'), 'icon' => 'groups'];
        $mobileItems[] = ['label' => 'Questões', 'route' => 'teacher.questions.index', 'active' => request()->routeIs('teacher.questions.*'), 'icon' => 'book'];
        $mobileItems[] = ['label' => 'Atividades', 'route' => 'teacher.activities.index', 'active' => request()->routeIs('teacher.activities.*', 'teacher.grading.*'), 'icon' => 'check'];
    } elseif (auth()->user()->isStudent()) {
        $mobileItems[] = ['label' => 'Atividades', 'route' => 'student.activities.index', 'active' => request()->routeIs('student.*'), 'icon' => 'check'];
    } elseif (auth()->user()->isAdmin()) {
        $mobileItems[] = ['label' => 'Usuários', 'route' => 'admin.users', 'active' => request()->routeIs('admin.users*'), 'icon' => 'groups'];
        $mobileItems[] = ['label' => 'Acadêmico', 'route' => 'admin.academic', 'active' => request()->routeIs('admin.academic'), 'icon' => 'book'];
    }
@endphp
<nav class="mobile-app-nav d-md-none" aria-label="Navegação principal no celular">
    @foreach($mobileItems as $item)
        <a class="mobile-app-nav-link @if($item['active']) is-active @endif" href="{{ route($item['route']) }}" @if($item['active']) aria-current="page" @endif>
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                @switch($item['icon'])
                    @case('home')<path d="M3 11 12 4l9 7v9h-6v-6H9v6H3z"/>@break
                    @case('groups')<path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 20v-2a4 4 0 0 0-3-3.87M16 2.13a4 4 0 0 1 0 7.75"/>@break
                    @case('book')<path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zM8 8h8M8 12h8M8 16h5"/>@break
                    @case('check')<path d="m9 11 3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>@break
                @endswitch
            </svg>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
