<?php

namespace App\Application\Services;

use App\Domain\Identity\Models\User;

class TeacherTutorial
{
    public function payload(User $user): array
    {
        $definition = config('tutorials.teacher');

        return [
            'role' => 'teacher',
            'version' => $definition['version'],
            'owner' => $user->public_id,
            'autoOpen' => $user->teacher_tutorial_seen_at === null && request()->routeIs('dashboard'),
            'endpoint' => route('teacher.tutorial.seen'),
            'welcome' => $definition['welcome'],
            'steps' => array_map(fn (array $step) => array_merge($step, ['url' => route($step['route'])]), $definition['steps']),
        ];
    }
}
