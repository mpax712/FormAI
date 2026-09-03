<?php

namespace App\Http\Controllers\Teacher;

use App\Application\Actions\DispatchAiGradingAction;
use App\Application\Actions\CancelExpiredAiGradingRunsAction;
use App\Application\Actions\ReviewSubmissionAction;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Infrastructure\AI\AiProviderConfiguration;
use App\Domain\Grading\Enums\GradingRunStatus;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Submissions\Enums\SubmissionStatus;
use App\Domain\Submissions\Models\Answer;
use App\Domain\Submissions\Models\Submission;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GradingController extends Controller
{
    public function show(Submission $submission, AiProviderConfiguration $configuration): View
    {
        $this->authorize('grade', $submission);
        $submission->load(['student', 'activity', 'answers.activityQuestion', 'answers.gradingDecision', 'answers.latestGradingRun.suggestion']);

        $aiConfigured = $configuration->isConfigured();
        $aiKeyName = $configuration->keyEnvironmentName();

        return view('teacher.grading.show', compact('submission', 'aiConfigured', 'aiKeyName'));
    }

    public function aiStatus(Submission $submission, CancelExpiredAiGradingRunsAction $cancelExpired): JsonResponse
    {
        $this->authorize('grade', $submission);
        $cancelExpired->execute($submission);
        $submission->load(['answers.activityQuestion', 'answers.latestGradingRun']);

        $essayAnswers = $submission->answers->filter(
            fn (Answer $answer) => $answer->activityQuestion->type === QuestionType::Essay
        );
        $runs = $essayAnswers->pluck('latestGradingRun')->filter();
        $activeStatuses = [GradingRunStatus::Pending, GradingRunStatus::Processing, GradingRunStatus::RetryableFailed];
        $active = $runs->filter(fn ($run) => in_array($run->status, $activeStatuses, true));
        $failed = $runs->where('status', GradingRunStatus::PermanentlyFailed);
        $succeeded = $runs->where('status', GradingRunStatus::Succeeded);
        $state = match (true) {
            $active->isNotEmpty() => 'processing',
            $failed->isNotEmpty() => 'failed',
            $runs->isNotEmpty() => 'completed',
            default => 'idle',
        };

        return response()->json([
            'state' => $state,
            'processed' => $succeeded->count() + $failed->count(),
            'requested' => $runs->count(),
            'total_essay_answers' => $essayAnswers->count(),
            'message' => match ($state) {
                'processing' => 'A IA está analisando as respostas. Você pode aguardar nesta tela ou entrar na correção manual.',
                'completed' => 'A análise da IA foi concluída. As sugestões já estão disponíveis para revisão.',
                'failed' => 'A IA não conseguiu concluir uma ou mais correções. O pedido foi encerrado e a correção manual continua disponível.',
                default => 'Nenhuma correção com IA está em andamento.',
            },
            'errors' => $failed->map(fn ($run) => [
                'answer_id' => $run->answer_id,
                'message' => $run->error_message ?: 'O serviço de IA não informou o motivo da falha.',
            ])->values(),
        ]);
    }

    public function generateAll(Submission $submission, DispatchAiGradingAction $action): RedirectResponse
    {
        $this->authorize('grade', $submission);
        $this->ensureCanGrade($submission);
        $submission->load('answers.activityQuestion');
        $answers = $submission->answers->filter(
            fn (Answer $answer) => $answer->activityQuestion->type === QuestionType::Essay
        );
        if ($answers->isEmpty()) {
            return back()->withErrors(['ai' => 'Esta entrega não possui respostas dissertativas.']);
        }

        try {
            DB::transaction(function () use ($answers, $action, $submission): void {
                $runs = $answers->map(fn (Answer $answer) => $action->execute($answer));
                $hasPendingWork = $runs->contains(fn ($run) => in_array($run->status, [GradingRunStatus::Pending, GradingRunStatus::Processing, GradingRunStatus::RetryableFailed], true));
                if ($hasPendingWork) {
                    Submission::query()->whereKey($submission->id)->where('status', SubmissionStatus::Submitted)
                        ->update(['status' => SubmissionStatus::Processing]);
                }
            });
        } catch (DomainException $exception) {
            return back()->withErrors(['ai' => $exception->getMessage()]);
        }

        return back()->with('status', 'Correção com IA solicitada para todas as questões dissertativas.');
    }

    public function generateOne(Submission $submission, Answer $answer, DispatchAiGradingAction $action): RedirectResponse
    {
        $this->authorize('grade', $submission);
        $this->ensureCanGrade($submission);
        abort_unless($answer->submission_id === $submission->id, 404);

        try {
            DB::transaction(function () use ($answer, $action, $submission): void {
                $action->execute($answer);
                Submission::query()->whereKey($submission->id)->where('status', SubmissionStatus::Submitted)
                    ->update(['status' => SubmissionStatus::Processing]);
            });
        } catch (DomainException $exception) {
            return back()->withErrors(['ai' => $exception->getMessage()]);
        }

        return back()->with('status', 'Correção com IA solicitada para a questão selecionada.');
    }

    public function review(Request $request, Submission $submission, ReviewSubmissionAction $action): RedirectResponse
    {
        $this->authorize('grade', $submission);
        $data = $request->validate(['grades' => ['nullable', 'array'], 'grades.*.score' => ['required', 'numeric', 'min:0'], 'grades.*.feedback' => ['nullable', 'string', 'max:5000']]);
        try {
            $action->execute($submission, $request->user(), $data['grades'] ?? []);
        } catch (DomainException $e) {
            return back()->withErrors(['grades' => $e->getMessage()]);
        }

        return back()->with('status', 'Correcao salva. O resultado ainda nao foi publicado.');
    }

    public function release(Submission $submission): RedirectResponse
    {
        $this->authorize('grade', $submission);
        if ($submission->status !== SubmissionStatus::Reviewed) {
            return back()->withErrors(['release' => 'Revise todas as respostas antes de publicar.']);
        }
        DB::transaction(function () use ($submission) {
            $locked = Submission::query()->lockForUpdate()->findOrFail($submission->id);
            $locked->update(['status' => SubmissionStatus::Released, 'released_at' => now()]);
            $locked->activity()->update(['status' => ActivityStatus::Released, 'released_at' => now()]);
        });

        return back()->with('status', 'Nota e feedback publicados para o aluno.');
    }

    public function reopen(Submission $submission): RedirectResponse
    {
        $this->authorize('grade', $submission);
        abort_if($submission->status === SubmissionStatus::Released, 422, 'Resultados publicados nao podem ser reabertos.');
        $submission->update(['status' => SubmissionStatus::Draft, 'submitted_at' => null, 'reopened_until' => now()->addDay(), 'version' => $submission->version + 1]);

        return back()->with('status', 'Entrega reaberta por 24 horas para o aluno.');
    }

    private function ensureCanGrade(Submission $submission): void
    {
        abort_unless(in_array($submission->status, [SubmissionStatus::Submitted, SubmissionStatus::Processing], true), 422, 'Esta entrega não aceita novas correções com IA.');
    }
}
