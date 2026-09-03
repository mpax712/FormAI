@extends('layouts.app')
@section('title', 'Administracao · FormAI')
@section('content')
<div class="d-flex justify-content-between align-items-center"><div><h1>Administracao</h1><p class="text-secondary">Acessos nesta area sao auditados.</p></div><div><a class="btn btn-outline-primary" href="{{ route('admin.academic') }}">Gestao academica</a> <a class="btn btn-primary" href="{{ route('admin.users') }}">Gerenciar usuarios</a></div></div>
<div class="row g-3 mb-4">@foreach($metrics as $label=>$value)<div class="col-6 col-lg-2"><div class="card p-3 h-100"><div class="metric fs-2">{{ $value }}</div><small class="text-secondary">{{ str_replace('_',' ',$label) }}</small></div></div>@endforeach</div>
<div class="card p-4"><h2 class="h4">Auditoria recente</h2><div class="table-responsive"><table class="table"><thead><tr><th>Quando</th><th>Ator</th><th>Evento</th><th>Rota</th></tr></thead><tbody>@foreach($audits as $audit)<tr><td>{{ $audit->created_at->format('d/m H:i:s') }}</td><td>{{ $audit->actor?->name ?? 'Sistema' }}</td><td>{{ $audit->event }}</td><td>{{ $audit->route }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
