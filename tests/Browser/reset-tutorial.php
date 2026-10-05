<?php

if (getenv('APP_ENV') !== 'testing' || ! str_starts_with(getenv('DB_DATABASE') ?: '', sys_get_temp_dir().'/formai-tutorial-')) {
    throw new RuntimeException('An isolated tutorial test database is required.');
}
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Domain\Identity\Models\User::where('email', 'tutorial@example.test')->update(['teacher_tutorial_seen_at' => null]);
App\Domain\Identity\Models\User::where('email', 'aluno-tutorial@example.test')->update(['student_tutorial_seen_at' => null]);
