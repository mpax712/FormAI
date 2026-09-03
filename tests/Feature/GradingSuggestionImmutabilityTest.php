<?php

namespace Tests\Feature;

use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Models\ActivityQuestion;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Grading\Enums\GradingRunStatus;
use App\Domain\Grading\Models\GradingRun;
use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Submissions\Enums\SubmissionStatus;
use App\Domain\Submissions\Models\Submission;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingSuggestionImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_suggestion_cannot_be_changed_after_creation(): void
    {
        $teacher = User::factory()->teacher()->create(); $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'T', 'is_active' => true]);
        $activity = Activity::query()->create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'title' => 'A', 'status' => ActivityStatus::Grading, 'deadline_at' => now(), 'total_score' => 10]);
        $question = ActivityQuestion::query()->create(['activity_id' => $activity->id, 'type' => QuestionType::Essay, 'body' => 'Q', 'max_score' => 10, 'position' => 1]);
        $submission = Submission::query()->create(['activity_id' => $activity->id, 'student_id' => $student->id, 'status' => SubmissionStatus::Submitted]);
        $answer = $submission->answers()->create(['activity_question_id' => $question->id, 'response_text' => 'R']);
        $run = GradingRun::query()->create(['answer_id' => $answer->id, 'idempotency_key' => str_repeat('a', 64), 'status' => GradingRunStatus::Succeeded, 'provider' => 'fake', 'model' => 'fake', 'prompt_version' => 1]);
        $suggestion = $run->suggestion()->create(['score' => 5, 'criterion_scores' => [], 'evidence' => [], 'feedback' => 'F', 'confidence' => .5, 'warnings' => [], 'created_at' => now()]);
        $this->expectException(DomainException::class);
        $suggestion->update(['score' => 10]);
    }
}
