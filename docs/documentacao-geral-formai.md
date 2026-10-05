# FormAI — documentação geral do projeto

**Base da análise:** código e configuração local examinados em 30/09/2026. Este texto descreve a implementação presente no repositório. Configurações de produção, estado do banco remoto e disponibilidade real dos provedores externos exigem validação no respectivo ambiente.

## 1. O que é o sistema

O **FormAI** é uma aplicação web para criar, aplicar e corrigir atividades escolares. O professor organiza turmas e um banco de questões, publica atividades de múltipla escolha e/ou dissertativas, acompanha entregas e publica notas e feedbacks. O aluno entra em uma turma, responde, envia a atividade e consulta o resultado após a publicação. O administrador acompanha usuários e dados acadêmicos e pode alterar papéis e o estado das contas.

O propósito da IA é **sugerir** correções de respostas dissertativas. A correção objetiva é calculada pelo próprio sistema. O professor decide quando solicitar IA, revisa ou altera as notas e os comentários, salva a revisão e publica o resultado em uma ação separada. É possível corrigir manualmente quando a IA não estiver configurada ou falhar.

## 2. Perfis e funções

| Perfil | Funções implementadas |
| --- | --- |
| Professor | Cria turmas e códigos de acesso, envia convites por e-mail, aprova ou rejeita solicitações de entrada, mantém questões com alternativas ou rubricas, cria atividades, visualiza entregas, solicita sugestões de IA, corrige, reabre entregas ainda não publicadas e publica resultados. Há painel com estatísticas e tutorial. |
| Aluno | Cadastra-se por convite/código, acessa turmas aprovadas, responde com salvamento durante a edição, envia todas as respostas e consulta nota e feedback publicados. |
| Administrador | Consulta indicadores, usuários, dados acadêmicos e registros de auditoria; altera o papel e a ativação de contas. |

O cadastro público padrão do Fortify cria **professores**. O cadastro pelo código da turma cria alunos com solicitação pendente por padrão; quando o professor ativa a entrada automática, novos cadastros por código são aprovados sem intervenção dele. Convites válidos criam ou vinculam alunos à turma. As telas operacionais exigem autenticação, conta ativa e e-mail verificado; as rotas também restringem o perfil.

## 3. Funcionamento de ponta a ponta

1. O professor se cadastra, verifica o e-mail e cria uma turma. O sistema gera um código de oito caracteres. Ele pode convidar alunos por e-mail, aprovar pedidos feitos por código ou ativar a aprovação automática dos novos cadastros por código.
2. O professor cria questões de múltipla escolha ou dissertativas no banco de questões, com pontuação, resposta esperada, orientações e critérios de rubrica quando aplicável. Também pode criar questões diretamente na atividade.
3. A atividade é salva como rascunho, visualizada e publicada. As questões e suas opções/rubricas são copiadas para `activity_questions` como **snapshot**. A mudança posterior de uma questão do banco não reescreve uma atividade já publicada. O limite de implementação é 50 questões por atividade; o prazo pode ser opcional.
4. O aluno aprovado abre a atividade e salva cada resposta. O salvamento usa versões para detectar conflitos. A entrega exige resposta para todas as questões. Ao enviar, o sistema calcula a nota das questões de múltipla escolha e registra a entrega; a IA não é chamada automaticamente.
5. O professor escolhe correção manual ou pede IA para uma resposta, uma entrega, respostas selecionadas ou uma atividade. O pedido é colocado na fila `ai`. A aplicação cria um registro de execução, guarda um snapshot do pedido e seleciona o destino conforme o perfil de IA configurado.
6. O worker envia pergunta, resposta do aluno, resposta esperada, rubrica e instruções ao provedor. A saída estruturada passa por validação local de formato, critérios e limites de nota. Falhas podem ocasionar espera, nova tentativa ou troca para um destino alternativo previamente habilitado. No máximo quatro chamadas externas são feitas por execução.
7. O professor vê a sugestão e o feedback privado, confirma ou altera nota e feedback de cada resposta dissertativa e salva a revisão. A nota final soma pontos objetivos e decisões humanas. Só a ação de publicar torna o resultado visível ao aluno.
8. O professor pode reabrir por 24 horas uma entrega que ainda não teve resultado publicado. Versões de resposta/entrega impedem usar sugestões antigas depois de uma alteração.

Os estados principais das atividades são `draft`, `published`, `closed`, `grading`, `review_ready` e `released`. As entregas passam por `draft`, `submitted`, `processing`, `reviewed` e `released`.

## 4. Arquitetura e tecnologias

| Camada | Tecnologia e uso efetivo |
| --- | --- |
| Servidor | **PHP 8.3+**, **Laravel 13** (versão instalada na análise: 13.26.1). Rotas web, controllers, serviços, ações de negócio, jobs, policies, middleware e agendador. |
| Interface | **Blade** renderiza HTML no servidor; telas principais usam **Bootstrap 5.3.8** por CDN com SRI, CSS próprio em `public/css`, JavaScript próprio em `public/js` e layout responsivo. |
| Banco | **PostgreSQL** como conexão padrão, preparado para **Supabase Session Pooler** com SSL obrigatório; **Eloquent ORM**, Query Builder, migrations, transações, chaves estrangeiras e índices. Há conexão MySQL local opcional para contingência e SQLite para testes. |
| Assíncrono | Fila Laravel com driver de banco de dados para correções de IA e notificações. Worker e scheduler são processos necessários fora do servidor HTTP. |
| Cache e sessão | Sessões no banco; cache principal configurável. A configuração local usa cache em banco e o controle de quotas da IA usa cache de arquivos. Leituras privadas são armazenadas criptografadas e separadas pelo proprietário. |
| IA | Adaptadores HTTP para **OpenRouter**, **Gemini** e **OpenAI**. A configuração local seleciona OpenRouter para os três perfis (`economy`, `balanced`, `advanced`); os outros destinos estão desabilitados nela. Modelo e preços precisam ser conferidos no provedor antes de afirmar uso ou custo em produção. |
| E-mail | Notificações Laravel para verificação de e-mail, recuperação de senha e convites. A configuração local usa SMTP; o projeto também suporta `log` para desenvolvimento. |
| Testes | **PHPUnit** para testes de unidade/integração, **Playwright** para navegador e **axe-playwright** para acessibilidade. |

O repositório contém arquivos de **Vite/Tailwind**, mas o layout principal examinado carrega os assets estáticos e Bootstrap. Portanto, Vite/Tailwind não devem ser descritos como dependência das telas principais sem verificar uma implantação específica.

Organização do código: `app/Domain` contém modelos e enums; `app/Application` contém regras e ações; `app/Infrastructure/AI` contém integrações com provedores; `app/Http` contém rotas de entrada, controllers, validações, middleware e policies; `resources/views` contém as telas Blade; `database/migrations` define o esquema.

## 5. Banco de dados e relacionamento

O modelo central é: **usuário → turma → atividade → questões da atividade → entrega do aluno → respostas → sugestões/decisões de correção**. O banco usa IDs internos numéricos e, em várias entidades, ULIDs públicos para URLs.

| Grupo | Tabelas principais | Conteúdo |
| --- | --- | --- |
| Identidade | `users`, `password_reset_tokens`, `sessions`, `passkeys` | Contas, papéis, verificação de e-mail, senha em hash, 2FA, sessões e estrutura de passkeys. |
| Turmas | `classrooms`, `classroom_memberships`, `invitations` | Professor responsável, código da turma, alunos aprovados/pendentes, convites com validade e token em hash. |
| Conteúdo | `questions`, `question_options`, `rubric_criteria`, `activities`, `activity_questions` | Banco do professor, alternativas, critérios, atividade e cópia das questões usadas nela. |
| Entregas | `submissions`, `answers` | Uma entrega por aluno/atividade; respostas, versões, estados, prazos, nota objetiva e final. |
| Correção | `grading_runs`, `grading_attempts`, `grading_suggestions`, `grading_decisions`, `prompt_templates` | Pedidos à IA, tentativas, consumo/custo estimado, sugestões, confirmação humana e versão de prompt. |
| Operação | `jobs`, `failed_jobs`, `job_batches`, `cache`, `cache_locks`, `audit_logs`, `system_heartbeats`, `migrations` | Fila, cache, auditoria, monitoramento e histórico de alterações do esquema. |

Restrições importantes incluem e-mail único, código de turma único, uma associação aluno/turma, uma entrega aluno/atividade, uma resposta por questão na entrega e uma decisão humana por resposta. Chaves estrangeiras, índices e transações ajudam a manter consistência; exclusões de certas entidades se propagam às dependentes conforme as migrations.

O projeto documenta Supabase/PostgreSQL como banco alvo. **A configuração local consultada utiliza `DB_CONNECTION=pgsql` e `DB_SCHEMA=public`; o README menciona schema `laravel`.** O schema e as migrations efetivamente aplicadas em produção devem ser verificados nesse ambiente antes de fixar o nome no documento institucional. O arquivo `database/formai.sql` é um artefato SQL; a fonte de evolução do esquema são as migrations.

## 6. Segurança implementada

| Área | Mecanismo observado |
| --- | --- |
| Autenticação | **Laravel Fortify** para login, cadastro, recuperação de senha, verificação de e-mail e autenticação de dois fatores **TOTP** com códigos de recuperação. O hash de senha usa **Argon2id**, com rehash no login. A opção de passkeys tem tabela/configuração, mas não consta entre os recursos habilitados no `config/fortify.php`; não deve ser apresentada como autenticação disponível. |
| Sessão | Sessões persistidas no banco e criptografadas conforme configuração; cookie `HttpOnly`, `SameSite=Lax` e regeneração após login/cadastro. O atributo `Secure` depende da configuração do ambiente. |
| Acesso | Middleware de autenticação, e-mail verificado, conta ativa e perfil; **policies** de proprietário e matrícula limitam turmas, questões, atividades e entregas. O administrador tem permissão global pelo `Gate::before`. |
| Entrada e abuso | Validação server-side, token **CSRF** nos formulários Laravel, limites de requisição para login, 2FA, cadastro por código/convite, consulta de código de turma, envio de convites, autosave e pedidos de IA. Convites usam token aleatório cujo **hash SHA-256** é guardado no banco e têm validade de sete dias. |
| Transporte e navegador | PostgreSQL exige SSL. Em ambiente `production`, requisições HTTP são redirecionadas para HTTPS; em HTTPS são enviados **HSTS**. Há **CSP**, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, política de referência e de permissões. Respostas autenticadas recebem `Cache-Control: private, no-store`. Assets Bootstrap do CDN têm **SRI**. |
| Dados e IA | O snapshot de pedido à IA é criptografado no banco pelo cast `encrypted:array` com `APP_KEY`; o cache privado do servidor é criptografado. O pedido externo omite nome, e-mail e turma e usa identificador **HMAC-SHA256**. A resposta do aluno continua sendo enviada ao provedor; saída da IA é validada e a publicação depende do professor. Prompts tratam resposta do aluno/instruções adicionais como conteúdo não confiável. |
| Rastreabilidade | `audit_logs` registra acesso administrativo sensível e eventos de conta; logs HTTP recebem ID de correlação. Execuções e tentativas de IA registram estado, modelo, tokens e custo estimado quando o provedor retorna telemetria. |
| Operação | `GET /up` fornece liveness e `GET /health` verifica banco, heartbeat do scheduler, atraso de fila e configuração de IA. O scheduler encerra atividades vencidas, limpa jobs falhos e anonimiza campos de identidade de contas com pedido de exclusão após 30 dias. |

O uso do Eloquent/Query Builder com parâmetros vinculados reduz o risco de SQL injection nos fluxos examinados. As páginas Blade usam escaping padrão para dados apresentados. Essas medidas são controles de código; **não equivalem a uma auditoria de segurança ou teste de intrusão**.

### Limites e pontos que exigem cuidado no documento de segurança

- A configuração **local** consultada está em `APP_ENV=local`, `APP_DEBUG=true` e `SESSION_SECURE_COOKIE=false`. Esses valores não devem ser descritos como política de produção. Em produção, confirmar HTTPS, `APP_DEBUG=false` e cookie seguro.
- A CSP ainda permite `unsafe-inline` para estilos e depende do CDN para Bootstrap. Há uma superfície externa e uma exceção de estilo a reduzir quando viável.
- O cadastro público gera contas de professor. Se o produto exigir autorização institucional para novos professores, esse controle ainda precisa ser definido/implementado. A rota `POST /register` do Fortify não mostra `throttle` próprio na lista de rotas atual; o limite de cadastro por código não a cobre.
- Fotos de perfil ficam em `public/uploads/avatars`, isto é, em área pública. Não são dados privados. O caminho não está ignorado pelo `.gitignore` atual; revisar publicação/versionamento de arquivos enviados por usuários.
- A rotina de exclusão **desativa a conta imediatamente e anonimiza campos de identidade do usuário após 30 dias**; ela não demonstra eliminação integral de respostas, notas, logs, backups ou fotos. A política de retenção e a adequação à LGPD dependem de decisão e verificação próprias.
- A IA recebe texto da pergunta, resposta do aluno, resposta esperada, rubrica e orientações. Identidade direta é omitida, mas o conteúdo pode conter dados pessoais digitados pelo usuário. Retenção e uso desses dados pelo provedor dependem do contrato/configuração externa.
- A documentação descreve fallback opcional para MySQL local. Ele vem desabilitado na configuração local observada; sincronização de dados entre PostgreSQL e MySQL não está implementada pelo mecanismo de fallback.

## 7. Implantação e operação

Para funcionar integralmente, o ambiente requer PHP com `pdo`, `pdo_pgsql` e `openssl`, banco preparado e migrado, `APP_KEY`, URL da aplicação, e-mail configurado, processo HTTP, **worker de fila** e **scheduler**. `composer dev` inicia o conjunto de processos para desenvolvimento. Em produção, o worker deve ser supervisionado e o scheduler precisa executar continuamente; iniciar só o servidor web não processa correções de IA nem tarefas agendadas. A IA requer chave do provedor, destinos/perfis válidos e quotas configuradas. Sem ela, a correção manual permanece disponível.

Comandos de verificação fornecidos pelo projeto: `php artisan database:preflight`, `php artisan migrate`, `php artisan ai:preflight`, `php artisan mail:preflight`, `php artisan test` e `npm run test:e2e`. O preflight de IA valida configuração local, sem comprovar acesso remoto, preço ou capacidade do modelo. A existência de `GET /health` não comprova por si só a saúde de uma implantação até que seja chamado no ambiente desejado.

## 8. Escopo atual

Esta versão trabalha com **texto** para correção por IA. Não há upload de PDF, imagem ou áudio para a IA. A nota sugerida nunca é publicada automaticamente. O custo armazenado é estimado a partir dos tokens informados pelo provedor e dos preços configurados, portanto não substitui a fatura externa. O controle de quotas da IA usa cache de arquivos compartilhado por workers na **mesma máquina**; a arquitetura atual não garante coordenação dessas quotas em vários servidores.

## 9. Fontes principais no repositório

- `routes/web.php` e `bootstrap/app.php`: rotas e middleware.
- `app/Application/Actions/` e `app/Jobs/GenerateAiSuggestion.php`: fluxos da atividade, entrega, revisão e IA.
- `app/Infrastructure/AI/` e `config/ai_grading.php`: provedores, prompts, perfis, quotas e validação.
- `database/migrations/`: estrutura e evolução do banco.
- `app/Providers/FortifyServiceProvider.php`, `config/fortify.php`, `config/hashing.php`, `config/session.php`, `app/Policies/`, `app/Http/Middleware/`: autenticação e segurança.
- `routes/console.php` e `app/Http/Controllers/HealthController.php`: tarefas e saúde operacional.
- `composer.json`, `package.json`, `resources/views/layouts/app.blade.php`: dependências e interface.
