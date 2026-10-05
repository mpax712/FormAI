# Tutorial do professor

A definição de conteúdo, versão, seletores e destinos está em `config/tutorials.php`.
Os destinos são nomes de rotas verificados no servidor. Para acrescentar um passo,
adicione uma entrada em `steps` e um `data-tour` estável na view correspondente.
Passos com `interaction` exigem um clique real e devem incluir `instruction`.
O próximo passo define o destino da navegação; use somente páginas de leitura.
Aumente `version` para oferecer uma nova edição aos professores.

O progresso pertence ao usuário autenticado e é armazenado em `tutorial_progress`,
com chave única por usuário/tutorial/versão, estado, passo e data de conclusão.
Pausar não conclui. Pular e concluir encerram a versão. Ajuda permite retomar ou
reiniciar. A tabela separada permite acrescentar tutoriais para outros papéis.

## Movimento e acessibilidade

A partial `resources/views/tutorials/teacher.blade.php` fornece um assistente SVG
local, barra de progresso, instrução textual e estado de carregamento. O módulo
no final de `public/js/formai.js` controla foco, persistência, posicionamento e
animações com Web Animations e CSS em `public/css/formai.css`.

As transições usam transform/opacity e duram 160–300 ms. O pulso e a seta têm
apenas três ciclos. A confirmação usa o assistente; a conclusão mostra um ícone
de confirmação animado, sem biblioteca ou partículas. Todos os efeitos são
cancelados na troca de passo e no fechamento. O estado de salvamento interrompe
imediatamente as indicações de clique. As camadas decorativas não interceptam
cliques; `inert` mantém o fundo indisponível e preserva o alvo interativo real.

Scroll, resize, mudanças do DOM e ResizeObserver reposicionam as camadas usando
requestAnimationFrame sob demanda. Em telas curtas o balão reserva espaço para o
alvo e permite rolagem interna. A seta fica oculta se houver colisão. Em telas
pequenas o assistente é ocultado para preservar espaço.

`prefers-reduced-motion` é observado inclusive durante o tutorial: cancela as
animações em execução, retira efeitos decorativos e usa rolagem instantânea.
O texto, foco, destaque e navegação permanecem disponíveis.

## Validação

```sh
php vendor/bin/phpunit tests/Feature/TeacherTutorialTest.php
node --check public/js/formai.js
node node_modules/@playwright/test/cli.js test tests/Browser/tutorial.spec.js --workers=1
```

O teste de navegador inicia um servidor PHP e um banco SQLite temporário, com
login real. Não utiliza o banco configurado no `.env`. Exige `pdo_sqlite`.
`FORMAI_TEST_PHP_ARGS` aceita argumentos PHP em JSON, por exemplo
`["-d","extension=/caminho/pdo_sqlite.so"]` quando necessário.
Os assets Bootstrap usados nos testes devem estar em `FORMAI_BROWSER_ASSETS`
(padrão `/tmp/formai-v2-browser`), com `bootstrap.min.css` e
`bootstrap.bundle.min.js` da versão 5.3.8, igual ao layout.

A migration deve ser aplicada antes da disponibilização do tutorial:

```sh
php artisan migrate --path=database/migrations/2026_09_28_000001_create_tutorial_progress_table.php
```

Se estiver pendente, o painel continua funcionando e oculta o tutorial.

## Resultado da validação

- Tutorial no Playwright: **12 testes passaram**, incluindo cliques reais,
  persistência entre páginas e novo login, pausa, reinício, alvo ausente, teclado,
  viewport móvel, orientação, efeitos, cancelamento e redução de movimento.
- Axe: nenhuma violação detectada nos diálogos inicial e interativo avaliados.
- Regressão das telas e editores (`teacher-editors.spec.js` e `ui-v2.spec.js`):
  **11 testes passaram**.
- Feature do tutorial: **8 testes passaram, 45 verificações**.
- Sintaxe JavaScript: `node --check public/js/formai.js` passou.
- Suíte PHP completa: **156 testes, 664 verificações, 20 falhas e 2 erros**.
  O conjunto de falhas é idêntico ao da execução completa anterior: testes de
  protótipos antigos, expectativa de redirecionamento no cadastro e testes de IA
  que entram em conflito com bloqueios de pedidos simultâneos/cooldown.
  A suíte geral não está integralmente aprovada; a lógica de IA foi preservada.

Além dos comandos acima, foram executados:

```sh
node node_modules/@playwright/test/cli.js test tests/Browser/teacher-editors.spec.js tests/Browser/ui-v2.spec.js --workers=2
php vendor/bin/phpunit --log-junit /tmp/tour-final-phpunit.xml
```

Neste ambiente foi necessário carregar `pdo_sqlite` explicitamente por `-d
extension=/tmp/formai-tour-php/usr/lib/php/20230831/pdo_sqlite.so`, também fornecido
via `FORMAI_TEST_PHP_ARGS` aos testes de navegador. A extensão foi extraída em um
diretório temporário, sem mudança na configuração global do PHP. Foi removida uma
vírgula inválida antes da declaração XML de `phpunit.xml`.

## Arquivos da implementação

Criados para o tutorial:

- `config/tutorials.php`
- `database/migrations/2026_09_28_000001_create_tutorial_progress_table.php`
- `app/Domain/Onboarding/Models/TutorialProgress.php`
- `app/Application/Services/TeacherTutorial.php`
- `app/Http/Controllers/Teacher/TutorialController.php`
- `resources/views/tutorials/teacher.blade.php`
- `tests/Feature/TeacherTutorialTest.php`
- `tests/Browser/prepare-tutorial.php`
- `tests/Browser/tutorial.spec.js`
- `docs/tutorial-professor.md`

Integrações e ajustes em arquivos existentes:

- `routes/web.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/teacher/activities/index.blade.php`
- `resources/views/teacher/activities/form.blade.php`
- `resources/views/teacher/grading/_preferences.blade.php`
- `public/js/formai.js`
- `public/css/formai.css`
- `tests/Browser/render-teacher-pages.php` (fixture de professor que já concluiu o tutorial)
- `phpunit.xml` (correção da sintaxe XML)
