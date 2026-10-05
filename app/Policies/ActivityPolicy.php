<?php

namespace App\Policies;

use App\Domain\Activities\Models\Activity;
use App\Domain\Identity\Models\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTeacher() || $user->isStudent();
    }

    public function view(User $user, Activity $activity): bool
    {
        return $activity->teacher_id === $user->id || ($user->isStudent() && $activity->classroom->students()->whereKey($user->id)->exists());
    }

    public function create(User $user): bool
    {
        return $user->isTeacher() && $user->hasVerifiedEmail();
    }

    public function update(User $user, Activity $activity): bool
    {
        return $activity->teacher_id === $user->id;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $activity->teacher_id === $user->id;
    }

    public function grade(User $user, Activity $activity): bool
    {
        return $activity->teacher_id === $user->id;
    }
}
