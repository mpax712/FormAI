<?php

namespace Database\Seeders;

use App\Domain\Grading\Models\PromptTemplate;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        PromptTemplate::query()->firstOrCreate(
            ['key' => 'grading', 'version' => config('formai.prompt_version')],
            ['content' => config('formai.base_prompt'), 'is_active' => true],
        );

        if (app()->environment('local')) {
            User::factory()->create(['name' => 'Administrador FormAI', 'email' => 'admin@formai.local', 'role' => UserRole::Admin]);
        }
    }
}
