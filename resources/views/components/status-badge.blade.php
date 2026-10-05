@props(['status'])
@php
    $class = match($status) {
        'Ativo', 'Finalizada', 'Publicada', 'Disponível' => 'success',
        'Em andamento', 'Processando IA', 'Corrigindo' => 'info',
        'Revisão necessária', 'Convite pendente', 'Agendada' => 'warning',
        'Falha na IA', 'Encerrada' => 'danger',
        default => 'neutral',
    };
@endphp
<span class="status-badge status-{{ $class }}"><span></span>{{ $status }}</span>
