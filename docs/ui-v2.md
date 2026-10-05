# Interface V2

A interface usa os tokens compartilhados de `public/css/formai.css`, navegação superior e componentes responsivos. As entregas ocupam a largura disponível; questões e opções de IA ficam em seções expansíveis.

## Indicadores

- Professor: atividades publicadas nos últimos 30 dias por padrão, 90 dias, intervalo personalizado ou histórico completo. A data de publicação define o conjunto; os números mostram o estado atual. Filtros e paginação de dez atividades são preservados na URL.
- Aproveitamento: média das notas finais **publicadas**, dividida pela pontuação máxima da atividade e multiplicada por 100. Notas nulas e pontuação máxima zero não entram na média. Ausência de amostra não é zero. Cada atividade informa sua amostra.
- Próximos prazos: sete dias, independentemente do filtro de desempenho.
- Aluno: somente turmas aprovadas, entregas próprias, rascunhos, reaberturas e resultados publicados.
- Administração: usuários ativos por papel, gestão acadêmica, auditoria e informações operacionais. Espera/nova tentativa e falha definitiva aparecem separadamente.

O endpoint existente de estatísticas mantém os campos de atividades, IA e tokens para consumidores anteriores e acrescenta resumo, paginação e médias. O painel deixa de exibir tokens e solicitações de IA. O cache é privado por usuário e filtros, com duração de 30 segundos.

## Correção selecionada

`POST /professor/atividades/{activity}/corrigir-selecionados` recebe `submission_ids[]` (IDs públicos), `feedback_detail` e `intelligence_profile`.

A autorização e a validação de todo o conjunto antecedem o enfileiramento. O servidor relê e bloqueia as entregas na transação, ignora entregas inelegíveis ou com análise ativa e informa a quantidade ignorada. O dispatcher existente mantém limites, execução após commit e idempotência.

A busca e o filtro limpam a seleção. Selecionar todos considera apenas as entregas elegíveis visíveis. Antes do envio, a confirmação mostra entregas e questões dissertativas. A ação de toda a atividade permanece separada. A publicação das notas continua explícita, após revisão humana.

## Validação reproduzível

Requisitos: dependências Composer e npm instaladas, PHP com PDO SQLite, Chromium do Playwright. Os testes usam SQLite em memória e dados sintéticos; não chamam Supabase ou o provedor de IA.

```bash
php vendor/bin/phpunit tests/Feature/UiV2DashboardTest.php tests/Feature/TeacherAiGradingControlTest.php tests/Feature/TeacherReadCacheTest.php tests/Feature/FortifyAuthenticationTest.php tests/Feature/AuthenticationRoutesTest.php
mkdir -p /tmp/formai-v2-browser
curl -fsSL https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css -o /tmp/formai-v2-browser/bootstrap.min.css
curl -fsSL https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js -o /tmp/formai-v2-browser/bootstrap.bundle.min.js
npx playwright test tests/Browser/ui-v2.spec.js --workers=1
```

O Bootstrap fica local durante os testes do navegador. É possível trocar o diretório com `FORMAI_BROWSER_ASSETS`. Para carregar extensões PHP fora do diretório padrão, use `FORMAI_TEST_PHP_ARGS` com um array JSON de argumentos. Os caches de configuração e rotas de desenvolvimento são isolados dos testes.

O navegador verifica 23 telas em 360, 768 e 1440 pixels, mais viewport CSS de 720 × 450 (equivalente à área disponível em 1440 × 900 com zoom de 200%). Verifica também busca, seleção, confirmação, opções expandidas, nomes longos, erros extensos, teclado e regras automatizadas de acessibilidade. A equivalência de viewport não substitui uma avaliação manual com zoom nativo e tecnologias assistivas.

Na execução da suíte Feature completa, 19 falhas fora dos cenários V2 foram identificadas: 18 em `PrototypeScreensTest` (rotas /demo ausentes e textos antigos) e uma em `PasswordPolicyTest` (redirecionamento de cadastro esperado diferente do Fortify atual). Os fluxos de autenticação não foram modificados para satisfazer essas expectativas antigas.
