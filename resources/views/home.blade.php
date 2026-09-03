@extends('layouts.app')
@section('title', 'FormAI · Correção assistida por IA')
@section('body-class', 'landing-page')
@section('main-class', 'landing-main')

@section('content')
<section class="landing-hero" aria-labelledby="hero-title">
    <div class="ambient-orb ambient-orb-one" aria-hidden="true"></div>
    <div class="ambient-orb ambient-orb-two" aria-hidden="true"></div>
    <div class="container position-relative">
        <div class="row align-items-center gy-5 gx-xl-5">
            <div class="col-lg-7">
                <div class="hero-copy">
                    <span class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span> IA responsável para a educação</span>
                    <h1 id="hero-title">Menos tempo corrigindo.<br><span>Mais tempo ensinando.</span></h1>
                    <p class="hero-lead">Transforme respostas dissertativas em correções consistentes, explicáveis e rápidas — sem abrir mão do olhar de quem realmente conhece a turma.</p>
                    <div class="hero-actions">
                        <a class="btn btn-primary btn-lg" href="{{ route('register') }}">Sou professor <svg aria-hidden="true" viewBox="0 0 20 20"><path d="M4 10h12m-5-5 5 5-5 5"/></svg></a>
                        <a class="btn btn-quiet btn-lg" href="{{ route('class-code.create') }}">Sou aluno: acessar por código</a>
                    </div>
                    <a class="hero-help-link" href="#como-funciona">Veja como o FormAI funciona</a>
                    <ul class="hero-assurances" aria-label="Garantias do FormAI">
                        <li><span aria-hidden="true">✓</span> Professor decide a nota</li>
                        <li><span aria-hidden="true">✓</span> Respostas anonimizadas</li>
                        <li><span aria-hidden="true">✓</span> Comece sem cartão</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="product-stage" aria-label="Exemplo de correção assistida">
                    <div class="stage-glow" aria-hidden="true"></div>
                    <div class="product-window glass-surface">
                        <div class="product-bar">
                            <div class="product-dots" aria-hidden="true"><span></span><span></span><span></span></div>
                            <span>Correção em andamento</span>
                            <span class="live-pill"><i aria-hidden="true"></i> IA pronta</span>
                        </div>
                        <div class="product-body">
                            <div class="question-label">Questão dissertativa · 10 pontos</div>
                            <h2>Explique o impacto da urbanização no clima local.</h2>
                            <div class="answer-preview"><span>Resposta do aluno</span><p>O aumento de superfícies impermeáveis e a redução da vegetação contribuem para...</p></div>
                            <div class="grading-header">
                                <div><span>Sugestão por rubrica</span><strong>Pronta para sua revisão</strong></div>
                                <div class="score-ring" aria-label="Nota sugerida: 8,7 de 10"><strong>8,7</strong><small>/10</small></div>
                            </div>
                            <div class="criterion"><div><span>Domínio do conceito</span><strong>4,5 / 5</strong></div><div class="criterion-track"><i style="width:90%"></i></div></div>
                            <div class="criterion"><div><span>Argumentação</span><strong>2,5 / 3</strong></div><div class="criterion-track"><i style="width:83%"></i></div></div>
                            <div class="criterion"><div><span>Clareza</span><strong>1,7 / 2</strong></div><div class="criterion-track"><i style="width:85%"></i></div></div>
                            <div class="review-note"><span aria-hidden="true">✦</span><p><strong>Você continua no controle.</strong> Revise a evidência, ajuste a nota e só então publique.</p></div>
                        </div>
                    </div>
                    <div class="floating-chip floating-chip-top"><span aria-hidden="true">◆</span> Rubrica aplicada</div>
                    <div class="floating-chip floating-chip-bottom"><span aria-hidden="true">✓</span> Privacidade preservada</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="trust-strip" aria-label="Diferenciais essenciais">
    <div class="container"><p>Feito para a rotina real de professores</p><div class="trust-items"><span>Correção por critérios</span><i aria-hidden="true"></i><span>Feedback explicável</span><i aria-hidden="true"></i><span>Revisão humana</span><i aria-hidden="true"></i><span>Dados protegidos</span></div></div>
</section>

<section id="como-funciona" class="landing-section process-section" aria-labelledby="process-title">
    <div class="container">
        <div class="section-heading"><span class="eyebrow">Um fluxo simples</span><h2 id="process-title">Da atividade à nota, sem perder o contexto.</h2><p>O FormAI organiza o trabalho repetitivo para que sua energia fique onde faz diferença: na decisão pedagógica.</p></div>
        <ol class="process-line">
            <li><span class="step-number">01</span><div><h3>Crie com clareza</h3><p>Monte questões, respostas esperadas e rubricas com pesos definidos.</p></div></li>
            <li><span class="step-number">02</span><div><h3>Receba as entregas</h3><p>Alunos respondem em uma experiência simples, responsiva e com autosave.</p></div></li>
            <li><span class="step-number">03</span><div><h3>Revise a análise</h3><p>A IA sugere nota, evidências e feedback para cada critério da rubrica.</p></div></li>
            <li><span class="step-number">04</span><div><h3>Publique com confiança</h3><p>Você ajusta o que precisar e libera o resultado somente quando decidir.</p></div></li>
        </ol>
    </div>
</section>

<section class="landing-section clarity-section" aria-labelledby="clarity-title">
    <div class="container"><div class="row align-items-center gy-5 gx-lg-5">
        <div class="col-lg-5"><span class="eyebrow">Tecnologia com critério</span><h2 id="clarity-title">Inteligência artificial que apoia. Nunca substitui.</h2><p class="section-lead">Cada sugestão vem acompanhada de evidências e permanece separada da nota oficial. A decisão final é sempre humana.</p><a class="text-link" href="{{ route('register') }}">Conhecer o FormAI <span aria-hidden="true">→</span></a></div>
        <div class="col-lg-6 offset-lg-1"><div class="principles-list">
            <article><span class="principle-icon" aria-hidden="true">01</span><div><h3>Critérios visíveis</h3><p>Rubricas transformam expectativas em parâmetros claros para professor e aluno.</p></div></article>
            <article><span class="principle-icon" aria-hidden="true">02</span><div><h3>Privacidade desde o início</h3><p>Nome, e-mail e turma não acompanham a resposta enviada para análise.</p></div></article>
            <article><span class="principle-icon" aria-hidden="true">03</span><div><h3>Funciona mesmo sem IA</h3><p>Qualquer falha do provedor mantém a entrega intacta e a correção manual disponível.</p></div></article>
        </div></div>
    </div></div>
</section>

<section class="landing-cta"><div class="container"><div class="cta-inner glass-surface"><div><span class="eyebrow">Seu tempo vale mais</span><h2>Uma correção mais ágil começa com um processo melhor.</h2></div><div class="cta-action"><a class="btn btn-light btn-lg" href="{{ route('register') }}">Criar conta de professor</a><a class="cta-student-link" href="{{ route('class-code.create') }}">Aluno? Use o código da turma</a></div></div></div></section>
@endsection
