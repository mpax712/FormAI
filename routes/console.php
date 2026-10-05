<?php

use App\Application\Actions\CancelExpiredAiGradingRunsAction;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Grading\Enums\GradingRunStatus;
use App\Domain\Grading\Models\GradingRun;
use App\Domain\Identity\Models\User;
use App\Domain\Submissions\Models\Submission;
use App\Infrastructure\Observability\Models\SystemHeartbeat;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Console\Command;

Artisan::command('ai:preflight', function (): int {
    $failed = false;
    $profiles = app(\App\Infrastructure\AI\GradingProfiles::class);
    foreach ($profiles::PROFILES as $profile => $label) {
        foreach (array_keys($profiles::DETAILS) as $detail) {
            try {
                $destinations = $profiles->destinations($profile, $detail, 8192, 1);
                $this->info($label.' / '.$detail.': '.implode(' → ', array_map(fn ($d) => $d['provider'].'/'.$d['model'], $destinations)));
                if (count($destinations) < 2) $this->warn('Sem alternativa válida para este perfil.');
            } catch (\DomainException $exception) {
                $this->error($label.' / '.$detail.': '.$exception->getMessage());
                $failed = true;
            }
        }
    }
    try {
        $store = app(\App\Infrastructure\AI\ProviderTraffic::class)->store();
        $lock = $store->lock('ai:preflight:'.\Illuminate\Support\Str::uuid(), 5);
        if (! $lock->get()) throw new \RuntimeException('Trava indisponível.');
        $lock->release();
        $this->info('Cache e trava: acessíveis pelo usuário atual.');
    } catch (\Throwable) {
        $this->error('Cache/travas indisponíveis. Verifique as permissões do usuário do worker.');
        $failed = true;
    }
    $this->line('Validação local, sem chamadas pagas. Confirme capacidades e limites no painel de cada provedor. Pedidos maiores são revalidados ao entrar na fila.');
    return $failed ? Command::FAILURE : Command::SUCCESS;
})->purpose('Valida perfis, preços, limites e travas de IA sem chamar APIs.');

Artisan::command('mail:preflight', function (): int {
    $mailer = config('mail.default');
    $mailers = config('mail.mailers', []);

    if (! isset($mailers[$mailer])) {
        $this->error("MAIL_MAILER={$mailer} não existe em config/mail.php.");
        $this->line('Use MAIL_MAILER=log para desenvolvimento ou MAIL_MAILER=smtp para entrega real.');

        return Command::FAILURE;
    }

    if ($mailer !== 'smtp') {
        $this->info("MAIL_MAILER={$mailer}. Os e-mails não serão entregues externamente.");
        $this->info('Use php artisan mail:test seu-email@exemplo.com para gravar uma mensagem de teste no log.');

        return Command::SUCCESS;
    }

    $smtp = config('mail.mailers.smtp');
    $url = $smtp['url'] ?? null;
    $urlParts = is_string($url) && $url !== '' ? parse_url($url) : [];
    $host = $urlParts['host'] ?? $smtp['host'] ?? null;
    $port = (int) ($urlParts['port'] ?? $smtp['port'] ?? 0);
    $username = $urlParts['user'] ?? $smtp['username'] ?? null;
    $password = $urlParts['pass'] ?? $smtp['password'] ?? null;
    $scheme = $urlParts['scheme'] ?? $smtp['scheme'] ?? null;
    $failed = false;

    if (! is_string($host) || $host === '' || $port < 1 || $port > 65535) {
        $this->error('MAIL_HOST e MAIL_PORT são inválidos.');

        return Command::FAILURE;
    }

    if (! in_array($scheme, [null, 'smtp', 'smtps'], true)) {
        $this->error('MAIL_SCHEME deve ser smtp, smtps ou null.');
        $failed = true;
    }

    if (! filled($username) || ! filled($password)) {
        $this->error('MAIL_USERNAME e MAIL_PASSWORD não estão configurados.');
        $this->line('Dica: para simplificar, você pode configurar tudo em uma única MAIL_URL.');
        $failed = true;
    } else {
        $this->info('Credenciais SMTP: configuradas');
    }

    $from = (string) config('mail.from.address');
    if (! filter_var($from, FILTER_VALIDATE_EMAIL) || str_ends_with($from, '.local')) {
        $this->error('MAIL_FROM_ADDRESS deve ser um remetente real e verificado.');
        $failed = true;
    } else {
        $this->info("Remetente: {$from}");
    }

    $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
    if (in_array($appHost, ['localhost', '127.0.0.1'], true)) {
        $this->warn('APP_URL aponta para localhost; destinatários externos não conseguirão abrir os links.');
    }

    $addresses = gethostbynamel($host);
    if ($addresses === false) {
        $this->error("DNS SMTP: não foi possível resolver {$host}.");

        return Command::FAILURE;
    }
    $this->info('DNS SMTP: '.implode(', ', $addresses));

    $socketHost = $scheme === 'smtps' ? 'ssl://'.$host : $host;
    $socket = @fsockopen($socketHost, $port, $errorNumber, $errorMessage, 10);
    if ($socket === false) {
        $this->error("TCP SMTP {$host}:{$port}: {$errorMessage} ({$errorNumber}).");

        return Command::FAILURE;
    }
    fclose($socket);
    $this->info("TCP SMTP {$host}:{$port}: ok");

    return $failed ? Command::FAILURE : Command::SUCCESS;
})->purpose('Valida a configuração e o alcance do servidor SMTP sem enviar mensagens.');

Artisan::command('mail:test {recipient : Endereço que receberá a mensagem}', function (string $recipient): int {
    $validator = Validator::make(['recipient' => $recipient], ['recipient' => ['required', 'email:rfc']]);
    if ($validator->fails()) {
        $this->error('Informe um destinatário de e-mail válido.');

        return Command::INVALID;
    }

    try {
        Mail::raw('Este é um teste de entrega SMTP do FormAI.', function ($message) use ($recipient): void {
            $message->to($recipient)->subject('Teste de e-mail do FormAI');
        });

        $this->info(config('mail.default') === 'log'
            ? 'Mensagem registrada no log; nenhuma entrega SMTP foi realizada.'
            : 'Mensagem aceita pelo transporte configurado. Verifique a caixa de entrada e o spam.');

        return Command::SUCCESS;
    } catch (\Throwable $exception) {
        $this->error('Falha ao enviar: '.$exception->getMessage());

        return Command::FAILURE;
    }
})->purpose('Envia uma mensagem simples pelo transporte de e-mail configurado.');

Artisan::command('database:prepare-supabase', function (): int {
    if (config('database.default') !== 'pgsql') {
        $this->error('DB_CONNECTION deve ser pgsql.');

        return Command::FAILURE;
    }

    $schema = config('database.connections.pgsql.search_path');

    if (! is_string(config('database.connections.pgsql.url')) || config('database.connections.pgsql.url') === '') {
        $this->error('DB_URL não está configurada.');

        return Command::FAILURE;
    }

    if (! is_string($schema) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema)) {
        $this->error('DB_SCHEMA deve conter apenas letras, números e underscore.');

        return Command::FAILURE;
    }

    try {
        DB::unprepared(sprintf('create schema if not exists "%s"', $schema));
        $this->info("Schema {$schema} está pronto.");

        return Command::SUCCESS;
    } catch (\Throwable $exception) {
        $this->error('Não foi possível preparar o schema: '.$exception->getMessage());

        return Command::FAILURE;
    }
})->purpose('Cria o schema PostgreSQL configurado antes das migrations.');

Artisan::command('database:preflight {--allow-pending : Não falha quando há migrations pendentes}', function (): int {
    $failed = false;

    foreach (['pdo_pgsql', 'openssl'] as $extension) {
        if (! extension_loaded($extension)) {
            $this->error("Extensão {$extension} não está carregada.");
            $failed = true;
        } else {
            $this->info("Extensão {$extension}: ok");
        }
    }

    if (! extension_loaded('intl')) {
        $this->warn('Extensão intl ausente: comandos de inspeção como db:table podem falhar.');
    } else {
        $this->info('Extensão intl: ok');
    }

    if (config('database.default') !== 'pgsql') {
        $this->error('DB_CONNECTION deve ser pgsql.');

        return Command::FAILURE;
    }

    $connection = config('database.connections.pgsql');
    $url = $connection['url'] ?? null;
    if (! is_string($url) || $url === '') {
        $this->error('DB_URL não está configurada.');

        return Command::FAILURE;
    }

    if (str_contains($url, 'DATABASE_URL=') || str_contains($url, 'DB_URL=')) {
        $this->error('DB_URL contém um prefixo de variável indevido. Informe somente a URL PostgreSQL.');

        return Command::FAILURE;
    }

    $urlParts = parse_url($url);
    if ($urlParts === false || ! in_array($urlParts['scheme'] ?? null, ['postgres', 'postgresql'], true)) {
        $this->error('DB_URL não é uma URL PostgreSQL válida.');

        return Command::FAILURE;
    }

    $host = $urlParts['host'] ?? $connection['host'] ?? null;
    $port = (int) ($urlParts['port'] ?? $connection['port'] ?? 5432);
    $database = ltrim((string) ($urlParts['path'] ?? ''), '/');
    $username = rawurldecode((string) ($urlParts['user'] ?? ''));

    if (! is_string($host) || ! str_ends_with($host, '.pooler.supabase.com')) {
        $this->error('DB_URL deve usar o host do Session Pooler do Supabase.');

        return Command::FAILURE;
    }

    if ($port !== 5432) {
        $this->error('DB_URL deve usar o Session Pooler na porta 5432.');

        return Command::FAILURE;
    }

    if ($database !== 'postgres') {
        $this->error('O banco informado em DB_URL deve ser postgres.');

        return Command::FAILURE;
    }

    if (! str_starts_with($username, 'postgres.')) {
        $this->error('O usuário de DB_URL deve estar no formato postgres.PROJECT_REF.');

        return Command::FAILURE;
    }

    if (($connection['sslmode'] ?? null) !== 'require') {
        $this->error('SSL deve estar configurado com sslmode=require.');

        return Command::FAILURE;
    }

    $addresses = gethostbynamel($host);
    if ($addresses === false) {
        $this->error("DNS: não foi possível resolver {$host}.");
        return Command::FAILURE;
    }
    $this->info('DNS: '.implode(', ', $addresses));

    $socket = @fsockopen($host, $port, $errorNumber, $errorMessage, 5);
    if ($socket === false) {
        $this->error("TCP {$host}:{$port}: {$errorMessage} ({$errorNumber}).");

        return Command::FAILURE;
    }
    fclose($socket);
    $this->info("TCP {$host}:{$port}: ok");

    try {
        DB::selectOne('select 1 as connected');
        $this->info('PostgreSQL com SSL: ok');

        $schema = $connection['search_path'] ?? null;
        if (! is_string($schema) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema)) {
            $this->error('DB_SCHEMA é inválido.');

            return Command::FAILURE;
        }

        $schemaExists = DB::selectOne(
            'select exists(select 1 from pg_namespace where nspname = :schema) as present',
            ['schema' => $schema],
        );
        if (! (bool) ($schemaExists->present ?? false)) {
            $this->error("Schema {$schema} não existe. Execute database:prepare-supabase.");

            return Command::FAILURE;
        }
        $this->info("Schema {$schema}: ok");

        $essentialTables = ['users', 'sessions', 'cache', 'jobs', 'failed_jobs'];
        $existingTables = DB::table('information_schema.tables')
            ->where('table_schema', $schema)
            ->whereIn('table_name', $essentialTables)
            ->pluck('table_name')
            ->all();
        $missingTables = array_values(array_diff($essentialTables, $existingTables));
        if ($missingTables !== []) {
            $this->error('Tabelas essenciais ausentes: '.implode(', ', $missingTables));
            $failed = true;
        } else {
            $this->info('Tabelas essenciais: ok');
        }

        $roleConstraint = DB::selectOne(<<<'SQL'
            select exists(
                select 1
                from pg_constraint c
                join pg_namespace n on n.oid = c.connamespace
                where n.nspname = :schema
                  and c.conname = 'users_role_check'
                  and c.contype = 'c'
            ) as present
        SQL, ['schema' => $schema]);
        if (! (bool) ($roleConstraint->present ?? false)) {
            $this->error('Constraint users_role_check não encontrada.');
            $failed = true;
        } else {
            $this->info('Constraint users_role_check: ok');
        }

        DB::beginTransaction();
        try {
            DB::statement('create temporary table formai_preflight_write_check (value integer) on commit drop');
            DB::table('formai_preflight_write_check')->insert(['value' => 1]);
            $this->info('Escrita transacional: ok (revertida)');
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        $migrator = app('migrator');
        $repository = $migrator->getRepository();
        if (! $repository->repositoryExists()) {
            $this->error('Tabela de migrations não encontrada. Execute database:prepare-supabase e php artisan migrate.');

            return Command::FAILURE;
        }

        $ran = $repository->getRan();
        $files = array_keys($migrator->getMigrationFiles($migrator->paths()));
        $pending = array_values(array_diff($files, $ran));

        if ($pending !== []) {
            $this->warn('Migrations pendentes: '.implode(', ', $pending));
            $failed = $failed || ! $this->option('allow-pending');
        } else {
            $this->info('Migrations: ok');
        }
    } catch (\Throwable $exception) {
        $this->error('PostgreSQL: '.$exception->getMessage());
        $failed = true;
    }

    return $failed ? Command::FAILURE : Command::SUCCESS;
})->purpose('Valida PHP, DNS, TCP, SSL, banco e migrations antes do deploy.');

Schedule::call(function () {
    SystemHeartbeat::query()->updateOrCreate(['name' => 'scheduler'], ['last_seen_at' => now(), 'metadata' => ['host' => gethostname() ?: 'unknown']]);
})->everyMinute()->name('scheduler-heartbeat')->withoutOverlapping();

Schedule::command('queue:work database --queue=ai,default --stop-when-empty --max-time=100 --tries=2 --timeout=90')
    ->everyMinute()->name('short-lived-queue-worker')->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('model:prune')->daily();

Schedule::call(function () {
    $timeout = (int) config('ai_grading.queue_timeout_seconds', 86400);
    $submissionIds = GradingRun::query()
        ->whereIn('status', [GradingRunStatus::Pending, GradingRunStatus::Processing, GradingRunStatus::RetryableFailed])
        ->where(fn ($q) => $q->where('expires_at', '<=', now())->orWhere(fn ($legacy) => $legacy->whereNull('expires_at')->where('created_at', '<=', now()->subSeconds($timeout))))
        ->with('answer:id,submission_id')
        ->get()
        ->pluck('answer.submission_id')
        ->filter()
        ->unique();

    Submission::query()->whereKey($submissionIds->all())->each(
        fn (Submission $submission) => app(CancelExpiredAiGradingRunsAction::class)->execute($submission)
    );
})->everyMinute()->name('cancel-expired-ai-grading')->withoutOverlapping();

Schedule::call(function () {
    Activity::query()->where('status', ActivityStatus::Published->value)->where('deadline_at', '<=', now())->each(function (Activity $activity) {
        $activity->update(['status' => $activity->submissions()->whereNotNull('submitted_at')->exists() ? ActivityStatus::Grading : ActivityStatus::Closed]);
    });
})->everyMinute()->name('close-expired-activities')->withoutOverlapping();

Schedule::call(function () {
    User::query()->whereNull('anonymized_at')->whereNotNull('deleted_requested_at')->where('deleted_requested_at', '<=', now()->subDays(30))->each(function (User $user) {
        $user->forceFill([
            'name' => 'Usuario removido '.$user->public_id,
            'email' => 'deleted+'.$user->public_id.'@invalid.local',
            'password' => Hash::make(str()->random(64)),
            'email_verified_at' => null,
            'mfa_code_hash' => null,
            'mfa_expires_at' => null,
            'anonymized_at' => now(),
        ])->save();
    });
})->daily()->name('anonymize-deleted-accounts')->withoutOverlapping();
