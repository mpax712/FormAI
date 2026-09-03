<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="PERT — planejamento de projetos com clareza, previsibilidade e controle.">
    <title>PERT | Planejamento inteligente de projetos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --ink:#142d36; --muted:#60747a; --paper:#f7f4ec; --white:#fffdf8; --teal:#0c7773; --teal-dark:#075d5a; --mint:#a7d9ca; --sun:#f2b84b; --line:rgba(20,45,54,.13); }
        * { box-sizing:border-box; }
        html { scroll-behavior:smooth; }
        body { margin:0; color:var(--ink); background:var(--paper); font-family:"DM Sans",sans-serif; -webkit-font-smoothing:antialiased; }
        a { color:inherit; text-decoration:none; }
        .shell { width:min(1180px,calc(100% - 40px)); margin:0 auto; }
        .nav { height:82px; display:flex; align-items:center; justify-content:space-between; }
        .brand { display:inline-flex; align-items:center; gap:12px; font:800 21px/1 "Manrope",sans-serif; }
        .brand-mark { width:42px; height:42px; display:grid; place-items:center; color:white; background:var(--teal); border-radius:13px; box-shadow:0 8px 20px rgba(12,119,115,.2); }
        .nav-links { display:flex; align-items:center; gap:32px; color:var(--muted); font-weight:600; font-size:14px; }
        .nav-links a:hover { color:var(--teal); }
        .nav-cta { padding:11px 18px; color:white!important; background:var(--ink); border-radius:999px; }
        .hero { min-height:calc(100vh - 82px); display:grid; grid-template-columns:1.08fr .92fr; align-items:center; gap:70px; padding:72px 0 110px; }
        .eyebrow { display:inline-flex; align-items:center; gap:10px; color:var(--teal); font-size:13px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; }
        .eyebrow::before { content:""; width:28px; height:2px; background:var(--sun); }
        h1 { max-width:720px; margin:22px 0 24px; font:800 clamp(48px,6.2vw,82px)/1.02 "Manrope",sans-serif; letter-spacing:-.055em; }
        h1 span { color:var(--teal); }
        .lead { max-width:620px; margin:0; color:var(--muted); font-size:clamp(17px,1.7vw,20px); line-height:1.65; }
        .actions { display:flex; flex-wrap:wrap; align-items:center; gap:14px; margin-top:36px; }
        .button { display:inline-flex; align-items:center; justify-content:center; gap:10px; min-height:52px; padding:0 24px; border-radius:14px; font-weight:700; transition:transform .2s,box-shadow .2s; }
        .button:hover { transform:translateY(-2px); }
        .button-primary { color:white; background:var(--teal); box-shadow:0 12px 26px rgba(12,119,115,.24); }
        .button-ghost { border:1px solid var(--line); background:rgba(255,255,255,.45); }
        .visual { position:relative; min-height:520px; }
        .visual::before { content:""; position:absolute; inset:2% 0 5% 9%; background:var(--mint); border-radius:48% 52% 46% 54%/42% 45% 55% 58%; opacity:.48; transform:rotate(-7deg); }
        .board { position:absolute; inset:54px 12px 38px 0; padding:28px; overflow:hidden; background:rgba(255,253,248,.94); border:1px solid rgba(255,255,255,.8); border-radius:28px; box-shadow:0 30px 80px rgba(20,45,54,.16); transform:rotate(2deg); }
        .board-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:34px; }
        .board-title { font:800 17px "Manrope",sans-serif; }
        .status { padding:7px 11px; color:var(--teal-dark); background:#dff2eb; border-radius:999px; font-size:11px; font-weight:700; }
        .timeline { position:relative; display:grid; gap:22px; }
        .timeline::before { content:""; position:absolute; top:19px; bottom:19px; left:18px; width:2px; background:#dbe7e2; }
        .task { position:relative; display:grid; grid-template-columns:38px 1fr auto; align-items:center; gap:14px; padding:13px; background:#fff; border:1px solid var(--line); border-radius:15px; box-shadow:0 5px 16px rgba(20,45,54,.05); }
        .task-dot { z-index:1; width:38px; height:38px; display:grid; place-items:center; color:white; background:var(--teal); border:5px solid var(--white); border-radius:50%; font-size:11px; font-weight:800; }
        .task:nth-child(2) .task-dot { background:var(--sun); }
        .task:nth-child(3) .task-dot { background:var(--ink); }
        .task strong { display:block; margin-bottom:3px; font-size:13px; }
        .task small { color:var(--muted); }
        .task-time { color:var(--teal); font-size:12px; font-weight:700; }
        .metric { position:absolute; right:-12px; bottom:3px; min-width:174px; padding:18px; background:var(--ink); color:white; border-radius:18px; box-shadow:0 18px 40px rgba(20,45,54,.25); }
        .metric small { color:#aec1c5; }
        .metric strong { display:block; margin-top:5px; color:var(--sun); font:800 29px "Manrope",sans-serif; }
        .section { padding:105px 0; }
        .section-white { background:var(--white); }
        .section-heading { display:grid; grid-template-columns:.8fr 1.2fr; gap:60px; align-items:end; margin-bottom:54px; }
        h2 { margin:13px 0 0; font:800 clamp(34px,4vw,52px)/1.08 "Manrope",sans-serif; letter-spacing:-.04em; }
        .section-heading p { margin:0; color:var(--muted); line-height:1.7; }
        .cards { display:grid; grid-template-columns:repeat(3,1fr); gap:18px; }
        .card { min-height:260px; padding:30px; border:1px solid var(--line); border-radius:22px; background:var(--paper); }
        .card-number { width:42px; height:42px; display:grid; place-items:center; color:var(--teal); background:white; border-radius:12px; font-weight:800; }
        .card h3 { margin:36px 0 12px; font:800 21px "Manrope",sans-serif; }
        .card p { margin:0; color:var(--muted); line-height:1.65; }
        .closing { padding:100px 0; }
        .closing-box { position:relative; overflow:hidden; padding:72px; color:white; background:var(--teal); border-radius:32px; }
        .closing-box::after { content:"PERT"; position:absolute; right:-18px; bottom:-46px; color:rgba(255,255,255,.07); font:800 150px "Manrope",sans-serif; letter-spacing:-.08em; }
        .closing h2 { position:relative; z-index:1; max-width:680px; margin:0 0 28px; }
        .closing .button { position:relative; z-index:1; color:var(--ink); background:var(--sun); }
        footer { display:flex; align-items:center; justify-content:space-between; padding:30px 0 42px; color:var(--muted); border-top:1px solid var(--line); font-size:13px; }
        @media (max-width:820px) { .nav-links a:not(.nav-cta){display:none}.hero{grid-template-columns:1fr;gap:28px;padding-top:54px}.visual{min-height:470px}.section-heading{grid-template-columns:1fr;gap:22px}.cards{grid-template-columns:1fr}.closing-box{padding:48px 28px} }
        @media (max-width:520px) { .shell{width:min(100% - 28px,1180px)}.nav{height:70px}.nav-cta{padding:10px 14px}.hero{min-height:auto;padding:48px 0 80px}h1{font-size:44px}.actions .button{width:100%}.visual{min-height:420px}.board{inset:36px 5px 26px;padding:19px}.task{grid-template-columns:34px 1fr}.task-time{display:none}.metric{right:0}.section{padding:76px 0}footer{align-items:flex-start;flex-direction:column;gap:12px} }
    </style>
</head>
<body>
    <header class="shell">
        <nav class="nav" aria-label="Navegação principal">
            <a class="brand" href="#inicio" aria-label="PERT — início"><span class="brand-mark">P</span> PERT</a>
            <div class="nav-links"><a href="#metodo">O método</a><a href="#beneficios">Benefícios</a><a class="nav-cta" href="#comecar">Começar agora</a></div>
        </nav>
    </header>
    <main>
        <section class="hero shell" id="inicio">
            <div>
                <span class="eyebrow">Gestão de projetos</span>
                <h1>Transforme incerteza em <span>direção.</span></h1>
                <p class="lead">Planeje atividades, estime prazos e visualize o caminho crítico do seu projeto com a metodologia PERT.</p>
                <div class="actions"><a class="button button-primary" href="#metodo">Conhecer o método <span aria-hidden="true">→</span></a><a class="button button-ghost" href="#beneficios">Ver benefícios</a></div>
            </div>
            <div class="visual" aria-label="Exemplo visual de um cronograma PERT">
                <div class="board">
                    <div class="board-head"><span class="board-title">Cronograma do projeto</span><span class="status">Em análise</span></div>
                    <div class="timeline">
                        <div class="task"><span class="task-dot">01</span><div><strong>Definição do escopo</strong><small>Objetivos e entregáveis</small></div><span class="task-time">2 dias</span></div>
                        <div class="task"><span class="task-dot">02</span><div><strong>Estimativa das atividades</strong><small>Cenários otimista e pessimista</small></div><span class="task-time">5 dias</span></div>
                        <div class="task"><span class="task-dot">03</span><div><strong>Análise do caminho crítico</strong><small>Prioridades e dependências</small></div><span class="task-time">3 dias</span></div>
                    </div>
                </div>
                <div class="metric"><small>Previsão de conclusão</small><strong>10 dias</strong></div>
            </div>
        </section>
        <section class="section section-white" id="metodo">
            <div class="shell">
                <div class="section-heading"><div><span class="eyebrow">Como funciona</span><h2>Um processo simples para projetos complexos.</h2></div><p>O PERT combina três estimativas de duração para produzir previsões mais realistas. Assim, sua equipe entende riscos antes que eles se transformem em atrasos.</p></div>
                <div class="cards" id="beneficios">
                    <article class="card"><span class="card-number">01</span><h3>Mapeie as atividades</h3><p>Organize entregas e dependências para enxergar o projeto de ponta a ponta.</p></article>
                    <article class="card"><span class="card-number">02</span><h3>Calcule os cenários</h3><p>Compare estimativas otimistas, prováveis e pessimistas com mais confiança.</p></article>
                    <article class="card"><span class="card-number">03</span><h3>Priorize o essencial</h3><p>Identifique o caminho crítico e concentre energia onde o prazo realmente depende.</p></article>
                </div>
            </div>
        </section>
        <section class="closing shell" id="comecar"><div class="closing-box"><h2>Planejamento claro para decisões melhores.</h2><a class="button" href="#inicio">Explorar o PERT <span aria-hidden="true">↑</span></a></div></section>
    </main>
    <footer class="shell"><span>© {{ date('Y') }} PERT. Planejamento e avaliação de projetos.</span><span>Laravel {{ app()->version() }}</span></footer>
</body>
</html>
