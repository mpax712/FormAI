<?php

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Submissions\Models\Submission;

class SubmissionPolicy
{
    public function view(User $user, Submission $submission): bool { return $submission->student_id === $user->id || $submission->activity->teacher_id === $user->id; }
    public function update(User $user, Submission $submission): bool { return $submission->student_id === $user->id; }
    public function grade(User $user, Submission $submission): bool { return $submission->activity->teacher_id === $user->id; }
}
