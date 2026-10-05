<?php

// Invoked only by tutorial.spec.js with a newly allocated temporary SQLite file.
if (getenv('APP_ENV') !== 'testing' || ! str_starts_with(getenv('DB_DATABASE') ?: '', sys_get_temp_dir().'/formai-tutorial-')) {
    throw new RuntimeException('An isolated tutorial test database is required.');
}
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
// This disposable test database needs no durability across machine crashes.
config(['database.connections.sqlite.journal_mode' => 'WAL', 'database.connections.sqlite.synchronous' => 'NORMAL']);
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
App\Domain\Identity\Models\User::factory()->teacher()->create(['email' => 'tutorial@example.test', 'password' => 'TutorialTest123!']);
App\Domain\Identity\Models\User::factory()->student()->create(['email' => 'aluno-tutorial@example.test', 'password' => 'TutorialTest123!']);
