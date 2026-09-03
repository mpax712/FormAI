<?php

namespace Tests\Feature;

use App\Application\DTOs\GradingRequest;
use App\Application\DTOs\GradingResult;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Models\ActivityQuestion;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Grading\Contracts\AiGradingProvider;
use App\Domain\Grading\Enums\GradingRunStatus;
use App\Domain\Grading\Models\GradingRun;
use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Submissions\Enums\SubmissionStatus;
use App\Domain\Submissions\Models\Submission;
use App\Jobs\GenerateAiSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AiGradingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_ai_job_does_not_overwrite_a_finished_human_review(): void
    {
        [$submission, $run] = $this->scenario(SubmissionStatus::Reviewed);

        (new GenerateAiSuggestion($run->id))->handle($this->provider());

        $this->assertSame(SubmissionStatus::Reviewed, $submission->fresh()->status);
        $this->assertSame(GradingRunStatus::Succeeded, $run->fresh()->status);
        $this->assertNotNull($run->fresh()->suggestion);
    }

    public function test_completed_ai_job_returns_processing_submission_to_manual_review_queue(): void
    {
        [$submission, $run] = $this->scenario(SubmissionStatus::Processing);

        (new GenerateAiSuggestion($run->id))->handle($this->provider());

        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->status);
        $this->assertSame(GradingRunStatus::Succeeded, $run->fresh()->status);
    }

    public function test_ai_job_is_cancelled_after_total_time_limit(): void
    {
        config(['formai.grading_timeout_seconds' => 30]);
        [$submission, $run] = $this->scenario(SubmissionStatus::Processing);
        $run->forceFill(['created_at' => now()->subSeconds(31)])->save();

        (new GenerateAiSuggestion($run->id))->handle($this->provider());

        $this->assertSame(GradingRunStatus::PermanentlyFailed, $run->fresh()->status);
        $this->assertSame('AiGradingTimeout', $run->fresh()->error_code);
        $this->assertNull($run->fresh()->suggestion);
        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->status);
    }

    public function test_failed_ai_job_keeps_submission_processing_while_another_run_is_pending(): void
    {
        [$submission, $failedRun] = $this->scenario(SubmissionStatus::Processing);
        $firstQuestion = $failedRun->answer->activityQuestion;
        $question = ActivityQuestion::query()->create([
            'activity_id' => $firstQuestion->activity_id,
            'type' => QuestionType::Essay,
            'body' => 'Explique outro aspecto do tema.',
            'max_score' => 10,
            'rubric_snapshot' => [],
            'position' => 2,
        ]);
        $otherAnswer = $submission->answers()->create([
            'activity_question_id' => $question->id,
            'response_text' => 'Outra resposta do aluno.',
            'version' => 1,
        ]);
        $pendingRun = GradingRun::query()->create([
            'answer_id' => $otherAnswer->id,
            'idempotency_key' => hash('sha256', 'pending-run'),
            'status' => GradingRunStatus::Pending,
            'provider' => 'openai',
            'model' => 'gpt-5.6-terra',
            'prompt_version' => 1,
        ]);

        (new GenerateAiSuggestion($failedRun->id))->failed(new RuntimeException('Falha de teste.'));

        $this->assertSame(GradingRunStatus::PermanentlyFailed, $failedRun->fresh()->status);
        $this->assertSame(GradingRunStatus::Pending, $pendingRun->fresh()->status);
        $this->assertSame(SubmissionStatus::Processing, $submission->fresh()->status);
    }

    private function provider(): AiGradingProvider
    {
        return new class implements AiGradingProvider
        {
            public function grade(GradingRequest $request): GradingResult
            {
                return new GradingResult(
                    score: 8,
                    criterionScores: [['criterion' => 'Qualidade geral da resposta', 'score' => 8, 'justification' => 'Boa resposta.']],
                    evidence: ['Trecho relevante.'],
                    feedback: 'Continue desenvolvendo seus argumentos.',
                    confidence: .9,
                    warnings: [],
                    inputTokens: 100,
                    outputTokens: 40,
                );
            }
        };
    }

    private function scenario(SubmissionStatus $status): array
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma', 'is_active' => true]);
        $activity = Activity::query()->create([
            'teacher_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'title' => 'Redação',
            'status' => ActivityStatus::Grading,
            'deadline_at' => now()->subHour(),
            'total_score' => 10,
        ]);
        $question = ActivityQuestion::query()->create([
            'activity_id' => $activity->id,
            'type' => QuestionType::Essay,
            'body' => 'Explique o tema.',
            'max_score' => 10,
            'rubric_snapshot' => [],
            'position' => 1,
        ]);
        $submission = Submission::query()->create([
            'activity_id' => $activity->id,
            'student_id' => $student->id,
            'status' => $status,
            'submitted_at' => now(),
        ]);
        $answer = $submission->answers()->create([
            'activity_question_id' => $question->id,
            'response_text' => 'Resposta do aluno.',
            'version' => 1,
        ]);
        $run = GradingRun::query()->create([
            'answer_id' => $answer->id,
            'idempotency_key' => hash('sha256', 'run-'.$status->value),
            'status' => GradingRunStatus::Pending,
            'provider' => 'openai',
            'model' => 'gpt-5.6-terra',
            'prompt_version' => 1,
        ]);

        return [$submission, $run];
    }
}
