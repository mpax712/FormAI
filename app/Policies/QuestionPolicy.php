<?php

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Models\Question;

class QuestionPolicy
{
    public function viewAny(User $user): bool { return $user->isTeacher(); }
    public function view(User $user, Question $question): bool { return $question->owner_id === $user->id; }
    public function create(User $user): bool { return $user->isTeacher() && $user->hasVerifiedEmail(); }
    public function update(User $user, Question $question): bool { return $question->owner_id === $user->id; }
    public function delete(User $user, Question $question): bool { return $question->owner_id === $user->id; }
}
