@extends('layouts.app')
@section('title', 'Painel do professor · FormAI')
@section('content')
<header class="v2-header"><div><span class="grading-eyebrow">Painel do professor</span><h1>Olá, {{ auth()->user()->name }}</h1><p>Acompanhe o aprendizado e as entregas das suas turmas.</p></div><a class="btn btn-primary" href="{{ route('teacher.activities.create') }}">Nova atividade</a></header>
<form method="get" class="card v2-filters" aria-label="Filtrar indicadores">
<label>Turma<select class="form-select" name="classroom"><option value="">Todas as turmas</option>@foreach($classrooms as $classroom)<option value="{{ $classroom->public_id }}" @selected($filters['classroom'] === $classroom->public_id)>{{ $classroom->name }}</option>@endforeach</select></label>
<label>Publicação da atividade<select class="form-select" name="period"><option value="30" @selected($filters['period'] === '30')>Últimos 30 dias</option><option value="90" @selected($filters['period'] === '90')>Últimos 90 dias</option><option value="custom" @selected($filters['period'] === 'custom')>Intervalo personalizado</option><option value="all" @selected($filters['period'] === 'all')>Todo o histórico</option></select></label>
<label>De (intervalo personalizado)<input class="form-control" type="date" name="from" value="{{ $filters['from'] }}"></label><label>Até (intervalo personalizado)<input class="form-control" type="date" name="to" value="{{ $filters['to'] }}"></label><button class="btn btn-primary">Aplicar filtros</button>
</form>
<div class="v2-metrics" data-tour="dashboard" data-dashboard-summary data-summary-url="{{ route('teacher.statistics') }}" data-user="{{ auth()->user()->public_id }}" data-filters="{{ json_encode($filters, JSON_THROW_ON_ERROR) }}">@foreach(['activities' => 'Atividades no período', 'awaiting_review' => 'Entregas para revisar', 'published' => 'Resultados publicados'] as $key => $label)<article class="card"><span class="metric" data-summary-key="{{ $key }}">{{ $statistics['summary'][$key] }}</span><span>{{ $label }}</span></article>@endforeach</div>
<div class="dashboard-summary-controls"><button class="btn btn-outline-primary" type="button" data-summary-refresh>Atualizar indicadores</button><span class="small text-secondary" data-summary-status role="status" aria-live="polite">Atualização em até 30 segundos.</span></div>
<section class="v2-section"><h2>Desempenho e participação</h2><p class="text-secondary">Somente notas publicadas. O período considera a publicação da atividade; os números mostram a situação atual. Atualização em até 30 segundos.</p>
@forelse(collect($activities->items())->groupBy('classroom_id') as $rows)
<h3 class="h5 mt-4">{{ $rows->first()['classroom'] }}</h3><div class="v2-activity-grid">
@foreach($rows as $row)
<article class="card v2-activity-stat"><h4 class="h5"><a href="{{ route('teacher.activities.show', $row['id']) }}">{{ $row['title'] }}</a></h4>
@if($row['average_percent'] !== null)<div class="v2-stat-heading"><strong>Aproveitamento médio</strong><span>{{ number_format($row['average_percent'], 1, ',', '.') }}%</span></div><progress class="v2-progress" max="100" value="{{ $row['average_percent'] }}" aria-label="Aproveitamento médio de {{ $row['title'] }}">{{ $row['average_percent'] }}%</progress><small>{{ $row['sample_count'] }} resultados publicados considerados</small>
@else<p class="v2-empty">{{ !$row['valid_maximum'] ? 'Sem pontuação máxima válida para calcular a média.' : 'Sem resultados publicados com nota.' }}</p>@endif
@php($total = $row['delivered'] + $row['pending'])
<div class="v2-stat-heading mt-3"><strong>Entregas</strong><span>{{ $row['delivered'] }} de {{ $total }}</span></div><progress class="v2-progress participation" max="{{ max(1, $total) }}" value="{{ $row['delivered'] }}" aria-label="Entregas de {{ $row['title'] }}">{{ $row['delivered'] }} de {{ $total }}</progress>
<dl class="v2-counts"><div><dt>Não entregues</dt><dd>{{ $row['pending'] }}</dd></div><div><dt>Aguardando revisão</dt><dd>{{ $row['awaiting_review'] }}</dd></div><div><dt>Revisadas / publicadas</dt><dd>{{ $row['reviewed'] }}</dd></div></dl>
<a class="btn btn-outline-primary" href="{{ route('teacher.activities.show', $row['id']) }}">Ver entregas e corrigir</a></article>
@endforeach</div>
@empty<div class="card v2-empty">Nenhuma atividade encontrada. Ajuste os filtros ou publique uma nova atividade.</div>@endforelse
<div class="mt-4">{{ $activities->links() }}</div></section>
<section class="card p-4 mt-4"><h2 class="h4">Próximos prazos</h2><p class="text-secondary">Próximos sete dias, independentemente dos filtros dos gráficos.</p><ul class="v2-link-list">@forelse($upcoming as $item)<li><a href="{{ route('teacher.activities.show', $item) }}">{{ $item->title }}</a><span>{{ $item->classroom->name }} · {{ $item->deadline_at->format('d/m H:i') }}</span></li>@empty<li>Nenhum prazo nos próximos sete dias.</li>@endforelse</ul></section>
@endsection
