# FormAI

Sistema de atividades escolares com correção objetiva automática e sugestão de correção por IA para respostas dissertativas. Toda nota sugerida pela IA precisa ser revisada e confirmada pelo professor antes da publicação.

## Configuração da IA

O provedor ativo é o Gemini. A integração com a OpenAI permanece disponível no código, mas está inativa enquanto `AI_PROVIDER=gemini`.

### Gemini (ativo)

1. Crie uma chave no [Google AI Studio](https://aistudio.google.com/apikey).
2. No arquivo `.env`, configure:

```env
AI_PROVIDER=gemini
GEMINI_API_KEY=sua_chave_do_gemini
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta
GEMINI_GRADING_MODEL=gemini-3.7-flash
GEMINI_TIMEOUT=45
GEMINI_MAX_OUTPUT_TOKENS=1200
GEMINI_TEMPERATURE=0.2
```

3. Recarregue as configurações:

```bash
php artisan config:clear
php artisan cache:clear
```

Nunca coloque a chave em arquivos JavaScript, templates Blade, commits ou capturas de tela.

O sistema usa a API REST `POST /v1beta/interactions`, autenticação pelo cabeçalho `x-goog-api-key` e resposta estruturada por JSON Schema. Consulte a [referência oficial da Gemini API](https://ai.google.dev/api) e a documentação de [saídas estruturadas](https://ai.google.dev/gemini-api/docs/structured-output).

### OpenAI (alternativa inativa)

O adaptador `OpenAiGradingProvider` e suas configurações continuam preservados. Para reativá-lo futuramente, configure:

```env
AI_PROVIDER=openai
OPENAI_API_KEY=sua_chave_de_projeto
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_GRADING_MODEL=gpt-5.6-terra
OPENAI_REASONING_EFFORT=low
OPENAI_TIMEOUT=45
OPENAI_MAX_OUTPUT_TOKENS=1200
```

Depois, execute novamente `php artisan config:clear`. A implementação continua usando a [Responses API da OpenAI](https://developers.openai.com/api/reference/resources/responses/methods/create), sem armazenamento da resposta no provedor.

## Fila de correção

Com XAMPP/Apache, mantenha este comando aberto em outro terminal:

```bash
php artisan queue:work database --queue=ai,default --tries=3 --timeout=60
```

Como alternativa, `php artisan schedule:work` executa o worker curto definido em `routes/console.php` a cada minuto. Em produção, configure um worker supervisionado e o agendador do Laravel.

O endpoint `/health` informa `ai: configured` quando a chave foi carregada e `ai: manual_only` quando o sistema está operando apenas com correção manual. Ele também informa o estado do agendador e o atraso da fila.

## Fluxo da correção

1. O aluno envia todas as respostas.
2. Questões objetivas são corrigidas localmente.
3. Nenhuma correção por IA é iniciada automaticamente com a entrega.
4. Na página da atividade, antes de abrir a entrega, o professor escolhe `Corrigir com IA` ou `Corrigir manualmente`.
5. Durante a correção manual, o professor também pode selecionar `Corrigir esta questão com IA` em uma resposta dissertativa específica.
6. Somente depois dessa escolha o servidor cria o job na fila `ai` e envia os textos necessários ao provedor configurado, solicitando saída estruturada por JSON Schema.
7. A sugestão de nota, critérios, evidências e feedback aparece na tela de correção.
8. O professor pode aceitar ou alterar tudo antes de salvar e publicar.

Ao criar uma questão dissertativa, o professor pode informar uma resposta esperada e seus critérios de correção. Os pesos dos critérios preenchidos devem totalizar `1` (por exemplo, `0.6` e `0.4`). Essas informações orientam a sugestão da IA.

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
