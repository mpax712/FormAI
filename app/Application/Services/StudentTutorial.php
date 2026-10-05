<?php

namespace App\Application\Services;

use App\Domain\Identity\Models\User;

class StudentTutorial
{
    public function payload(User $user): array
    {
        $definition = config('tutorials.student');

        return [
            'role' => 'student',
            'version' => $definition['version'],
            'owner' => $user->public_id,
            'autoOpen' => $user->student_tutorial_seen_at === null && request()->routeIs('dashboard'),
            'endpoint' => route('student.tutorial.seen'),
            'welcome' => $definition['welcome'],
            'steps' => array_map(fn (array $step) => array_merge($step, ['url' => route($step['route'])]), $definition['steps']),
        ];
    }
}
