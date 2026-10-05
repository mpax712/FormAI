<?php

namespace App\Http\Controllers\Teacher;

use App\Application\Actions\CancelExpiredAiGradingRunsAction;
use App\Application\Actions\DispatchAiGradingAction;
use App\Application\Actions\ReviewSubmissionAction;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Grading\Enums\GradingRunStatus;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Submissions\Enums\SubmissionStatus;
use App\Domain\Submissions\Models\Answer;
use App\Domain\Submissions\Models\Submission;
use App\Http\Controllers\Controller;
use App\Infrastructure\AI\AiProviderConfiguration;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GradingController extends Controller
{
    public function editActivityResults(Activity $activity): View
    {
        $this->authorize('grade', $activity);
        $submissions = $activity->submissions()->where('status', SubmissionStatus::Released)
            ->with(['student', 'answers.activityQuestion', 'answers.gradingDecision'])
            ->orderBy('submitted_at')->orderBy('id')->get();

        return view('teacher.grading.activity-results', compact('activity', 'submissions'));
    }

    public function republishActivityResults(Request $request, Activity $activity, ReviewSubmissionAction $action): RedirectResponse
    {
        $this->authorize('grade', $activity);
        $data = $request->validate([
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['required', 'string', 'distinct', Rule::exists('submissions', 'public_id')
                ->where('activity_id', $activity->id)->where('status', SubmissionStatus::Released->value)],
        ]);
        $submittedGrades = $request->input('grades', []);
        $selectedGrades = [];
        foreach ($data['selected'] as $publicId) {
            $selectedGrades[$publicId] = is_array($submittedGrades) ? ($submittedGrades[$publicId] ?? []) : [];
        }
        $grades = Validator::make(['grades' => $selectedGrades], [
            'grades' => ['required', 'array'],
            'grades.*.*.score' => ['required', 'numeric', 'min:0'],
            'grades.*.*.feedback' => ['nullable', 'string', 'max:5000'],
        ])->validate()['grades'];

        try {
            DB::transaction(function () use ($activity, $request, $data, $grades, $action): void {
                $submissions = $activity->submissions()->whereIn('public_id', $data['selected'])
                    ->orderBy('id')->lockForUpdate()->get();
                if ($submissions->count() !== count($data['selected'])) {
                    throw new DomainException('Uma entrega selecionada não está mais disponível. Atualize a página.');
                }
                foreach ($submissions as $submission) {
                    $submissionGrades = $grades[$submission->public_id] ?? null;
                    if (! is_array($submissionGrades)) {
                        throw new DomainException('Informe as notas de todas as entregas selecionadas.');
                    }
                    $action->executeAndPublish($submission, $request->user(), $submissionGrades);
                }
            });
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['grades' => $exception->getMessage()]);
        }

        return back()->with('status', count($data['selected']).' resultado(s) republicado(s) para os alunos.');
    }

    public function generateActivity(Request $request, \App\Domain\Activities\Models\Activity $activity, DispatchAiGradingAction $action): RedirectResponse
    {
        $this->authorize('grade', $activity);
        $options = app(\App\Infrastructure\AI\GradingProfiles::class)->options($request->only('feedback_detail', 'intelligence_profile', 'grading_strictness'), $activity);
        $count = 0;
        try {
            $activity->submissions()->whereIn('status', ['submitted', 'processing'])->chunkById(50, function ($submissions) use ($action, &$count, $options) {
                foreach ($submissions as $submission) {
                    DB::transaction(function () use ($submission, $action, &$count, $options) {
                        $locked = Submission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
                        if (! in_array($locked->status, [SubmissionStatus::Submitted, SubmissionStatus::Processing], true)) return;
                        $answers = $locked->answers()->whereHas('activityQuestion', fn ($q) => $q->where('type', 'essay'))->with('activityQuestion.activity')->get();
                        $pending = false;
                        foreach ($answers as $answer) {
                            $run = $action->execute($answer, $options);
                            $pending = $pending || in_array($run->status, [GradingRunStatus::Pending, GradingRunStatus::Processing, GradingRunStatus::RetryableFailed], true);
                        }
                        if ($pending) {
                            $locked->update(['status' => SubmissionStatus::Processing]);
                            $count++;
                        }
                    });
                }
            });
        } catch (DomainException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }
        return back()->with('status', "$count entregas com correção solicitada. Revise as sugestões antes de publicar os resultados.");
    }

    public function generateSelected(Request $request, \App\Domain\Activities\Models\Activity $activity, DispatchAiGradingAction $action): RedirectResponse
    {
        $this->authorize('grade', $activity);
        $data = $request->validate([
            'submission_ids' => ['required', 'array', 'min:1'],
            'submission_ids.*' => ['required', 'string', 'distinct', \Illuminate\Validation\Rule::exists('submissions', 'public_id')->where('activity_id', $activity->id)],
        ]);
        $options = app(\App\Infrastructure\AI\GradingProfiles::class)->options($request->only('feedback_detail', 'intelligence_profile', 'grading_strictness'), $activity);
        $count = 0;
        $skipped = 0;
        try {
            DB::transaction(function () use ($activity, $data, $options, $action, &$count, &$skipped) {
                $submissions = $activity->submissions()->whereIn('public_id', $data['submission_ids'])->orderBy('id')->lockForUpdate()->get();
                $skipped = count($data['submission_ids']) - $submissions->count();
                foreach ($submissions as $submission) {
                    $answers = $submission->answers()->whereHas('activityQuestion', fn ($q) => $q->where('type', 'essay'))->with(['activityQuestion.activity', 'latestGradingRun'])->get();
                    $active = [GradingRunStatus::Pending, GradingRunStatus::Processing, GradingRunStatus::RetryableFailed];
                    if (! in_array($submission->status, [SubmissionStatus::Submitted, SubmissionStatus::Processing], true)
                        || $answers->isEmpty() || $answers->contains(fn ($a) => in_array($a->latestGradingRun?->status, $active, true))) {
                        $skipped++;
                        continue;
                    }
                    $runs = $answers->map(fn ($answer) => $action->execute($answer, $options));
                    if ($runs->contains(fn ($run) => in_array($run->status, $active, true))) {
                        $submission->update(['status' => SubmissionStatus::Processing]);
                        $count++;
                    } else {
                        $skipped++;
                    }
                }
            });
        } catch (DomainException $exception) {
            return back()->withErrors(['ai' => $exception->getMessage()]);
        }
        return back()->with('status', "$count entregas com correção solicitada. $skipped ignoradas por estado atual ou pedido já registrado. Revise as sugestões antes de publicar.");
    }

    public function show(Submission $submission, AiProviderConfiguration $configuration): View
    {
        $this->authorize('grade', $submission);
        $submission->load(['student', 'activity', 'answers.activityQuestion', 'answers.gradingDecision', 'answers.latestGradingRun.suggestion', 'answers.gradingRuns.suggestion', 'answers.gradingRuns.attemptRecords']);
        $deliveryIds = $submission->activity->submissions()
            ->whereIn('status', [SubmissionStatus::Submitted, SubmissionStatus::Processing, SubmissionStatus::Reviewed, SubmissionStatus::Released])
            ->orderBy('submitted_at')->orderBy('id')->pluck('public_id');
        $deliveryPosition = $deliveryIds->search($submission->public_id);
        $previousDelivery = $deliveryPosition !== false && $deliveryPosition > 0 ? $deliveryIds[$deliveryPosition - 1] : null;
        $nextDelivery = $deliveryPosition !== false && $deliveryPosition < $deliveryIds->count() - 1 ? $deliveryIds[$deliveryPosition + 1] : null;

        $aiConfigured = $configuration->isConfigured();
        $aiKeyName = $configuration->keyEnvironmentName();
        $aiRuntimeWarning = $configuration->runtimeWarning();

        return view('teacher.grading.show', compact('submission', 'aiConfigured', 'aiKeyName', 'aiRuntimeWarning', 'deliveryIds', 'deliveryPosition', 'previousDelivery', 'nextDelivery'));
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
        $retrying = $runs->where('status', GradingRunStatus::RetryableFailed);
        $failed = $runs->where('status', GradingRunStatus::PermanentlyFailed);
        $succeeded = $runs->where('status', GradingRunStatus::Succeeded);
        $state = match (true) {
            $active->contains(fn ($run) => $run->wait_reason === 'fallback') => 'fallback',
            $active->contains(fn ($run) => in_array($run->wait_reason, ['rate_limit', 'provider_paused', 'quota_configuration'])) => 'waiting',
            $retrying->isNotEmpty() => 'retrying',
            $active->isNotEmpty() && $active->every(fn ($run) => $run->status === GradingRunStatus::Pending) => 'queued',
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
                'queued' => 'Pedido na fila de correção.',
                'waiting' => 'Aguardando capacidade ou limite da API. Nenhuma nova chamada foi feita.',
                'fallback' => 'Tentando um provedor alternativo configurado para este perfil.',
                'retrying' => 'Falha temporária. Uma nova tentativa espaçada foi agendada.',
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

    public function generateAll(Request $request, Submission $submission, DispatchAiGradingAction $action): RedirectResponse
    {
        $this->authorize('grade', $submission);
        $this->ensureCanGrade($submission);
        $options = app(\App\Infrastructure\AI\GradingProfiles::class)->options($request->only('feedback_detail', 'intelligence_profile', 'grading_strictness'), $submission->activity);
        $submission->load('answers.activityQuestion');
        $answers = $submission->answers->filter(
            fn (Answer $answer) => $answer->activityQuestion->type === QuestionType::Essay
        );
        if ($answers->isEmpty()) {
            return back()->withErrors(['ai' => 'Esta entrega não possui respostas dissertativas.']);
        }

        try {
            DB::transaction(function () use ($answers, $action, $submission, $options): void {
                $runs = $answers->map(fn (Answer $answer) => $action->execute($answer, $options));
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

    public function generateOne(Request $request, Submission $submission, Answer $answer, DispatchAiGradingAction $action): RedirectResponse
    {
        $this->authorize('grade', $submission);
        $this->ensureCanGrade($submission);
        $options = app(\App\Infrastructure\AI\GradingProfiles::class)->options($request->only('feedback_detail', 'intelligence_profile', 'grading_strictness'), $submission->activity);
        abort_unless($answer->submission_id === $submission->id, 404);

        try {
            DB::transaction(function () use ($answer, $action, $submission, $options): void {
                $run = $action->execute($answer, $options);
                if (in_array($run->status, [GradingRunStatus::Pending, GradingRunStatus::Processing, GradingRunStatus::RetryableFailed], true)) {
                    Submission::query()->whereKey($submission->id)->where('status', SubmissionStatus::Submitted)
                        ->update(['status' => SubmissionStatus::Processing]);
                }
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
        $republishing = $submission->status === SubmissionStatus::Released;
        try {
            $action->executeAndPublish($submission, $request->user(), $data['grades'] ?? []);
        } catch (DomainException $e) {
            return back()->withErrors(['grades' => $e->getMessage()]);
        }

        return back()->with('status', $republishing
            ? 'Nota e feedback republicados para o aluno.'
            : 'Nota e feedback publicados para o aluno.');
    }

    public function release(Submission $submission, ReviewSubmissionAction $action): RedirectResponse
    {
        $this->authorize('grade', $submission);
        try {
            $action->releaseReviewed($submission);
        } catch (DomainException $e) {
            return back()->withErrors(['release' => 'Revise todas as respostas antes de publicar.']);
        }

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
