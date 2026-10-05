@extends('layouts.app', ['role' => 'admin'])
@section('title', 'Painel administrativo')
@section('content')
<x-page-header eyebrow="Operação" title="Visão geral" description="Acompanhe a saúde do FormAI e o uso da escola em tempo real."><button class="btn btn-light" data-demo-action><i class="bi bi-download me-1"></i> Exportar relatório</button></x-page-header>
<div class="stats-grid">
    <x-stat-card label="Usuários ativos" value="94" icon="bi-people" tone="violet" trend="8% este mês" />
    <x-stat-card label="Provas criadas" value="38" icon="bi-file-earmark-text" tone="blue" trend="6 novas" />
    <x-stat-card label="Correções por IA" value="486" icon="bi-stars" tone="green" trend="12% este mês" />
    <x-stat-card label="Custo estimado" value="R$ 42,18" icon="bi-cash-stack" tone="orange" trend="R$ 0,09 / correção" :trend-up="false" />
</div>
<div class="dashboard-grid">
    <section class="panel"><div class="panel-header"><div><h2>Atividade da plataforma</h2><p>Correções processadas nos últimos sete dias</p></div><select class="form-select form-select-sm w-auto"><option>7 dias</option><option>30 dias</option></select></div><div class="panel-body pb-5"><div class="chart-placeholder"><span class="chart-column" data-label="Qui" style="height:34%"></span><span class="chart-column" data-label="Sex" style="height:57%"></span><span class="chart-column" data-label="Sáb" style="height:22%"></span><span class="chart-column" data-label="Dom" style="height:15%"></span><span class="chart-column" data-label="Seg" style="height:76%"></span><span class="chart-column" data-label="Ter" style="height:92%"></span><span class="chart-column" data-label="Hoje" style="height:68%"></span></div></div></section>
    <section class="panel"><div class="panel-header"><div><h2>Saúde dos serviços</h2><p>Atualizado há 1 minuto</p></div><x-status-badge status="Ativo" /></div><div class="panel-body"><div class="list-stack">
        <div class="list-row"><span class="list-icon tone-green"><i class="bi bi-hdd-stack"></i></span><span class="list-copy"><strong>Aplicação e banco</strong><small>Operacional · 99,96% neste mês</small></span><x-status-badge status="Ativo" /></div>
        <div class="list-row"><span class="list-icon"><i class="bi bi-stars"></i></span><span class="list-copy"><strong>Provedor de IA</strong><small>Resposta média em 11,4 s</small></span><x-status-badge status="Ativo" /></div>
        <div class="list-row"><span class="list-icon tone-blue"><i class="bi bi-envelope"></i></span><span class="list-copy"><strong>Envio de e-mails</strong><small>100% entregues nas últimas 24 h</small></span><x-status-badge status="Ativo" /></div>
        <div class="list-row"><span class="list-icon tone-orange"><i class="bi bi-list-task"></i></span><span class="list-copy"><strong>Fila de correção</strong><small>3 aguardando processamento</small></span><x-status-badge status="Em andamento" /></div>
    </div></div></section>
</div>
<section class="panel mt-4"><div class="panel-header"><div><h2>Eventos recentes</h2><p>Ações administrativas e alertas relevantes</p></div><a href="{{ route('demo.admin.metrics') }}" class="btn btn-sm btn-light">Ver auditoria</a></div><div class="table-responsive"><table class="table formai-table"><thead><tr><th>Evento</th><th>Responsável</th><th>Data</th><th>Origem</th></tr></thead><tbody>
    <tr><td><span class="table-title">Professor convidado</span><span class="table-subtitle">carlos@escola.edu.br</span></td><td>Rafael Lima</td><td>Hoje, 09:24</td><td>187.14.•••.•••</td></tr>
    <tr><td><span class="table-title">Nota alterada após sugestão</span><span class="table-subtitle">Tentativa #FA-2048</span></td><td>Marina Souza</td><td>Hoje, 08:51</td><td>187.14.•••.•••</td></tr>
    <tr><td><span class="table-title">Nova versão de prova publicada</span><span class="table-subtitle">Revolução Industrial · v2</span></td><td>Marina Souza</td><td>Ontem, 17:38</td><td>200.17.•••.•••</td></tr>
</tbody></table></div></section>
@endsection
