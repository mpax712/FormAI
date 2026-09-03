<?php

namespace App\Policies;

use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Models\User;

class ClassroomPolicy
{
    public function viewAny(User $user): bool { return $user->isTeacher(); }
    public function view(User $user, Classroom $classroom): bool { return $classroom->teacher_id === $user->id || $classroom->students()->whereKey($user->id)->exists(); }
    public function create(User $user): bool { return $user->isTeacher() && $user->hasVerifiedEmail(); }
    public function update(User $user, Classroom $classroom): bool { return $user->isTeacher() && $classroom->teacher_id === $user->id; }
    public function delete(User $user, Classroom $classroom): bool { return $this->update($user, $classroom) && ! $classroom->activities()->exists(); }
}
