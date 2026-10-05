@extends('layouts.app', ['role' => 'student'])
@section('title', 'Realizar prova')
@section('content')
<div class="exam-topbar"><div class="d-flex align-items-center gap-3"><a class="icon-button" href="{{ route('demo.student.exams') }}" aria-label="Sair da prova"><i class="bi bi-arrow-left"></i></a><div><b class="d-block small">Revolução Industrial</b><span class="question-meta">Questão 2 de 10</span></div><div class="ms-auto d-flex align-items-center gap-3"><span class="question-meta d-none d-sm-inline" id="saveStatus"><i class="bi bi-cloud-check text-success me-1"></i>Respostas salvas</span><span class="timer"><i class="bi bi-clock"></i><span id="examTimer">01:17:42</span></span></div></div></div>
<div class="exam-shell">
    <div class="d-flex align-items-center gap-3 mb-4"><div class="progress flex-grow-1"><div class="progress-bar" style="width:20%"></div></div><span class="question-meta">20% concluído</span></div>
    <section class="panel"><div class="panel-header"><div><span class="eyebrow">Questão 2 · Dissertativa</span><h1 class="h5 fw-bold mt-2 mb-0">Consequências sociais</h1></div><b>5,0 pontos</b></div><div class="panel-body p-md-4">
        <p class="question-text fs-6 lh-base">Explique duas consequências sociais da Revolução Industrial e relacione-as às transformações ocorridas nas cidades europeias do século XIX.</p>
        <div class="callout callout-info my-4"><i class="bi bi-lightbulb"></i><span>Desenvolva sua resposta com clareza. Cite relações de causa e consequência sempre que possível.</span></div>
        <label class="form-label" for="studentAnswer">Sua resposta</label><textarea class="form-control" id="studentAnswer" rows="12" placeholder="Digite sua resposta aqui...">A Revolução Industrial provocou um rápido crescimento das cidades, pois muitas pessoas deixaram o campo para trabalhar nas fábricas. Esse processo de urbanização ocorreu sem planejamento e gerou moradias precárias e problemas de saneamento. Além disso, formou-se uma nova classe trabalhadora assalariada, submetida a jornadas extensas e condições perigosas, o que posteriormente incentivou a organização de movimentos operários.</textarea>
        <div class="d-flex justify-content-between mt-2"><span class="question-meta"><span id="wordCount">59</span> palavras</span><span class="question-meta"><i class="bi bi-shield-check me-1"></i>Salvamento automático ativo</span></div>
    </div></section>
    <div class="d-flex justify-content-between gap-2 mt-4"><button class="btn btn-light px-4" data-demo-action><i class="bi bi-arrow-left me-1"></i> Anterior</button><div class="d-flex gap-2"><button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#submitModal">Entregar prova</button><button class="btn btn-primary px-4" data-demo-action>Próxima <i class="bi bi-arrow-right ms-1"></i></button></div></div>
    <div class="d-flex justify-content-center flex-wrap gap-2 mt-5">@for($i=1;$i<=10;$i)<button class="btn {{ $i === 2 ? 'btn-primary' : ($i === 1 ? 'btn-light text-success' : 'btn-light') }} btn-sm" style="width:36px" data-demo-action>{{ $i }}</button>@endfor</div>
</div>
<div class="modal fade" id="submitModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 rounded-4"><div class="modal-body p-4 text-center"><div class="stat-icon tone-orange mx-auto mb-3"><i class="bi bi-send"></i></div><h2 class="h5 fw-bold">Entregar a prova?</h2><p class="text-secondary small">Você respondeu 8 de 10 questões. Depois da confirmação não será possível alterar as respostas.</p><div class="callout callout-warning text-start my-4"><i class="bi bi-exclamation-triangle"></i><span>As questões 7 e 9 ainda não foram respondidas.</span></div><div class="d-flex gap-2"><button class="btn btn-light flex-fill" data-bs-dismiss="modal">Continuar respondendo</button><button class="btn btn-primary flex-fill" data-demo-action>Confirmar entrega</button></div></div></div></div></div>
@endsection
@push('scripts')
<script>
const answer = document.getElementById('studentAnswer');
const count = document.getElementById('wordCount');
const status = document.getElementById('saveStatus');
answer?.addEventListener('input', () => {
    count.textContent = answer.value.trim() ? answer.value.trim().split(/\s+/).length : 0;
    if (status) status.innerHTML = '<i class="bi bi-cloud-arrow-up text-secondary me-1"></i>Salvando...';
    window.clearTimeout(window.formaiSaveTimer);
    window.formaiSaveTimer = window.setTimeout(() => { if (status) status.innerHTML = '<i class="bi bi-cloud-check text-success me-1"></i>Respostas salvas'; }, 700);
});
</script>
@endpush
