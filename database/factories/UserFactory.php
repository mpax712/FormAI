<?php

namespace Database\Factories;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Teacher,
            'is_active' => true,
        ];
    }

    public function teacher(): static { return $this->state(['role' => UserRole::Teacher]); }
    public function student(): static { return $this->state(['role' => UserRole::Student]); }
    public function admin(): static { return $this->state(['role' => UserRole::Admin]); }
}
