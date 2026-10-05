# FormAI

Sistema de atividades escolares com correção objetiva automática e sugestão de correção por IA para respostas dissertativas. Toda nota sugerida pela IA precisa ser revisada e confirmada pelo professor antes da publicação.

Configuração dos três perfis, feedback privado, quotas compartilhadas e implantação: [guia de correção com IA](docs/ai-grading.md). Execute `php artisan ai:preflight` para validar a configuração local sem chamadas pagas.

## Banco de dados: Supabase

O FormAI usa PostgreSQL no **Session Pooler** do Supabase (porta `5432`), com SSL obrigatório e schema `laravel`. Copie a URL em **Supabase > Connect** para `DB_URL` no `.env`; não use o pooler transacional na porta `6543`.

Após trocar credenciais ou ambiente, execute:

```bash
php artisan config:clear
php artisan database:preflight
php artisan database:prepare-supabase
php artisan migrate
php artisan database:preflight
```

`database:preflight` verifica extensões PHP, formato da URL, DNS, alcance TCP, SSL, schema, tabelas essenciais, permissão de escrita, constraints e migrations pendentes. Um timeout em TCP significa bloqueio de rede/firewall ou restrição de rede no Supabase — não é erro de senha ou migration. Use `GET /up` para liveness sem dependência do banco e `GET /health` como readiness, que valida banco, scheduler e fila sem passar pelo middleware de sessão.

O servidor de produção precisa das extensões `pdo`, `pdo_pgsql` e `openssl`. Para executar a suíte local baseada em SQLite, instale também `pdo_sqlite`; a extensão `intl` é recomendada para os comandos de inspeção do Artisan.

O Fortify é o único fluxo de autenticação. As URLs canônicas são `/register`, `/login`, `/logout`, `/forgot-password`, `/reset-password`, `/email/verify` e `/two-factor-challenge`. As antigas páginas `/cadastro`, `/entrar` e `/senha/esqueci` apenas redirecionam para esse fluxo.

## Envio de e-mail

Por padrão, o projeto usa `MAIL_MAILER=log`. Assim a verificação de e-mail, recuperação de senha e convites podem ser testadas sem SMTP: o código de confirmação e os links de recuperação ou convite ficam em `storage/logs/laravel.log`.

Para desenvolvimento, não configure servidor de e-mail. Depois de cadastrar um usuário, abra o log, localize o código de 6 números e digite-o em `/email/verify`:

```bash
tail -f storage/logs/laravel.log
```

Para produção, configure o SMTP do seu provedor:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.exemplo.com
MAIL_PORT=587
MAIL_USERNAME=usuario
MAIL_PASSWORD=senha
MAIL_SCHEME=smtp
MAIL_FROM_ADDRESS=noreply@seudominio.com
MAIL_FROM_NAME="${APP_NAME}"
APP_URL=https://app.seudominio.com
```

Para Gmail, use a porta `465` com `MAIL_SCHEME=smtps` e uma senha de app do Google:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=seu-email@gmail.com
MAIL_PASSWORD=sua-senha-de-app
MAIL_SCHEME=smtps
MAIL_FROM_ADDRESS=seu-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

Depois de alterar o `.env`, execute:

```bash
php artisan config:clear
php artisan mail:preflight
php artisan mail:test seu-email@exemplo.com
```

O `mail:preflight` não envia mensagens nem revela credenciais; ele valida remetente, DNS e conectividade TCP quando `MAIL_MAILER=smtp`. Convites são enfileirados, portanto exigem o worker `queue:work`; verificação de endereço e recuperação de senha são disparadas pelo Fortify.

Ao expor uma senha ou chave, revogue-a no provedor e gere outra antes de atualizar o `.env`. O arquivo `.env.example` contém somente placeholders e `.env` não deve ser versionado.

## Configuração da IA

O ambiente está preparado para OpenRouter com `stealth/space-bunny-alpha`, usando `OPENROUTER_API_KEY` no servidor. Os três perfis usam a Space Bunny Alpha com raciocínio habilitado e resposta em modo JSON Object; Gemini e OpenAI ficam desabilitados como alternativas. `AI_PROVIDER` seleciona o provedor padrão; os destinos `AI_*` habilitam a correção em fila.

Configure a chave no `.env` e confira os modelos, preços e limites seguindo [docs/ai-grading.md](docs/ai-grading.md). O `.env.example` habilita OpenRouter nos três perfis com a Space Bunny Alpha, preços zerados e limite local de 50 chamadas/dia (40 após a margem de 80%). Chamadas repetidas por erro também contam para o limite; disponibilidade e limites externos continuam sujeitos ao OpenRouter. Valide localmente com:

```bash
php artisan config:cache
php artisan ai:preflight
```

O preflight não chama APIs nem valida acesso remoto. Nunca exponha chaves em JavaScript, templates ou commits. Não execute `cache:clear` com correções em andamento: o cache mantém as reservas de quota e travas dos workers.

Após uma falha, uma nova tentativa para a mesma questão pode ser solicitada depois de cinco minutos. O novo pedido substitui a execução falha no banco; os limites do provedor continuam contabilizados. Solicitações por professor também são limitadas a 3 por minuto e 20 por hora.

O OpenRouter usa [`POST /api/v1/chat/completions`](https://openrouter.ai/docs/api/api-reference/chat/create-a-chat-completion) com mensagens de sistema/usuário. A Space Bunny Alpha recebe `reasoning.enabled=true` e `response_format=json_object`; o schema completo é incluído na instrução e a resposta passa pela validação local. JSON Schema não é usado porque o endpoint atual do modelo não o respeita de forma confiável. A preferência `provider.require_parameters` também não é enviada, pois ela elimina o endpoint disponível. O Gemini usa `generateContent`; a OpenAI usa a Responses API com `store=false`. As saídas são validadas antes de salvar sugestões. Timeout HTTP de até 60 segundos, prazo global de 24 horas e até quatro chamadas externas por execução. As notas nunca são publicadas automaticamente.

## Fila de correção

No desenvolvimento local, inicie a aplicação com:

```bash
composer dev
```

Esse comando mantém servidor, fila e agendador ativos. O agendador fecha atividades expiradas, registra o heartbeat do sistema e também executa um worker curto de contingência.

Com XAMPP/Apache, se não usar `composer dev`, mantenha estes comandos abertos em terminais separados:

```bash
php artisan queue:work database --queue=ai,default --tries=2 --timeout=90
php artisan schedule:work
```

Em produção, configure um worker supervisionado e o agendador do Laravel. Apenas iniciar o Apache não processa a fila de correção.

O endpoint `/health` informa `ai: configured` quando a chave foi carregada e `ai: manual_only` quando o sistema está operando apenas com correção manual. Ele também informa o estado do agendador e o atraso da fila.

### Certificados SSL no Windows/XAMPP

O PHP usado pelo Apache e o PHP usado no terminal podem carregar arquivos `php.ini` diferentes. Confira cada instalação com `php --ini` e com a tela `phpinfo()` do Apache. Em ambas, `curl.cainfo` e `openssl.cafile` devem apontar para um pacote de certificados CA existente e atualizado. Depois de alterar o `php.ini` do XAMPP, reinicie o Apache. O erro `cURL error 60` indica que essa validação de certificado falhou; nunca contorne o problema desativando a verificação SSL.

## Fluxo da correção

1. O aluno envia todas as respostas.
2. Questões objetivas são corrigidas localmente.
3. Nenhuma correção por IA é iniciada automaticamente com a entrega.
4. Na página da atividade, antes de abrir a entrega, o professor escolhe `Corrigir com IA` ou `Corrigir manualmente`.
5. Durante a correção manual, o professor também pode selecionar `Corrigir esta questão com IA` em uma resposta dissertativa específica.
6. Somente depois dessa escolha o servidor cria o job na fila `ai` e envia os textos necessários ao provedor configurado, solicitando saída estruturada por JSON Schema.
7. A sugestão de nota, critérios, evidências e feedback aparece na tela de correção.
8. O professor pode aceitar ou alterar tudo antes de salvar e publicar.

Ao criar uma questão dissertativa, o professor pode informar uma resposta esperada e critérios de correção em pontos. Quando houver critérios, seus pontos devem totalizar exatamente a pontuação da questão (por exemplo, `6` e `4` em uma questão de `10` pontos). Um prompt geral opcional da atividade orienta a IA em todas as respostas dissertativas.

Se a chave estiver ausente ou a API falhar depois das tentativas configuradas, a entrega continua disponível para correção manual.

## Verificação

```bash
php artisan migrate
php artisan test
```

Depois de iniciar a fila, envie uma resposta dissertativa de teste e confira a tela do professor. Não existe upload de PDF, imagem, áudio ou outros anexos para a IA nesta versão; somente texto é enviado.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
