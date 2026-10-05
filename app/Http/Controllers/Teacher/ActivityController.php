<?php

namespace App\Http\Controllers\Teacher;

use App\Application\Actions\PublishActivityAction;
use App\Application\Actions\SaveActivityDraftAction;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\QuestionBank\Models\Question;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\SaveActivityRequest;
use App\Infrastructure\AI\AiProviderConfiguration;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:180'],
            'classroom_id' => ['nullable', 'integer', Rule::exists('classrooms', 'id')->where('teacher_id', $request->user()->id)],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));

        $activities = Activity::query()
            ->where('teacher_id', $request->user()->id)
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
            ->when($filters['classroom_id'] ?? null, fn ($query, $classroomId) => $query->where('classroom_id', $classroomId))
            ->with(['classroom' => fn ($query) => $query->select('id', 'public_id', 'name')->withCount('students')])
            ->withCount(['submissions as delivered_count' => fn ($query) => $query
                ->whereNotNull('submitted_at')
                ->whereExists(fn ($membership) => $membership->selectRaw('1')
                    ->from('classroom_memberships')
                    ->whereColumn('classroom_memberships.user_id', 'submissions.student_id')
                    ->whereColumn('classroom_memberships.classroom_id', 'activities.classroom_id')
                    ->where('classroom_memberships.status', 'approved'))])
            ->latest()
            ->paginate(15)
            ->withQueryString();
        $classrooms = Classroom::query()->where('teacher_id', $request->user()->id)
            ->whereHas('activities', fn ($query) => $query->where('teacher_id', $request->user()->id))
            ->orderBy('name')->get(['id', 'public_id', 'name', 'is_active']);

        return view('teacher.activities.index', compact('activities', 'classrooms', 'search'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Activity::class);
        $classrooms = Classroom::query()->where('teacher_id', $request->user()->id)->where('is_active', true)->orderBy('name')->get();
        $questionRows = [];
        $selectedBankQuestionIds = $this->requestedBankQuestionIds($request);
        $selectedBankQuestions = $this->selectedBankQuestions($request, null, $selectedBankQuestionIds);
        $selectedBankQuestionIds = $selectedBankQuestions->pluck('id')->all();
        $bankQuestions = $this->bankQuestions($request, $selectedBankQuestionIds);

        return view('teacher.activities.form', compact('classrooms', 'bankQuestions', 'questionRows', 'selectedBankQuestionIds', 'selectedBankQuestions'));
    }

    public function store(SaveActivityRequest $request, SaveActivityDraftAction $save, PublishActivityAction $publish): RedirectResponse
    {
        $this->authorize('create', Activity::class);
        try {
            $activity = DB::transaction(function () use ($request, $save, $publish): Activity {
                $activity = $save->execute(null, $request->user(), $request->validated());

                return $request->input('intent') === 'publish' ? $publish->execute($activity) : $activity;
            });
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['questions' => $e->getMessage()]);
        }
        Cache::forget('dashboard:teacher:'.$request->user()->id);
        if ($request->input('intent') === 'preview') {
            return redirect()->route('teacher.activities.preview', $activity);
        }
        if ($request->input('intent') === 'publish') {
            return redirect()->route('teacher.activities.show', $activity)->with('status', 'Atividade criada e publicada. As perguntas foram congeladas em um snapshot.');
        }

        return redirect()->route('teacher.activities.edit', $activity)->with('status', 'Rascunho e perguntas salvos.');
    }

    public function show(Activity $activity, AiProviderConfiguration $configuration): View
    {
        $this->authorize('view', $activity);
        $activity->load(['classroom', 'questions', 'submissions.student', 'submissions.answers.activityQuestion', 'submissions.answers.latestGradingRun']);
        $aiConfigured = $configuration->isConfigured();
        $aiKeyName = $configuration->keyEnvironmentName();
        $aiRuntimeWarning = $configuration->runtimeWarning();

        return view('teacher.activities.show', compact('activity', 'aiConfigured', 'aiKeyName', 'aiRuntimeWarning'));
    }

    public function preview(Activity $activity): View
    {
        $this->authorize('update', $activity);
        $activity->load(['classroom', 'questions']);

        return view('teacher.activities.preview', compact('activity'));
    }

    public function edit(Request $request, Activity $activity): View
    {
        $this->authorize('update', $activity);
        $metadataOnly = $activity->submissions()->exists();
        $classrooms = Classroom::query()->where('teacher_id', $request->user()->id)
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $activity->classroom_id))
            ->orderBy('name')->get();
        $activity->load('questions');
        $storedBankQuestionIds = $activity->questions->whereNotNull('source_question_id')->pluck('source_question_id')->all();
        $selectedBankQuestionIds = $this->requestedBankQuestionIds($request, $storedBankQuestionIds);
        $selectedBankQuestions = $this->selectedBankQuestions($request, $activity, $selectedBankQuestionIds);
        $selectedBankQuestionIds = $selectedBankQuestions->pluck('id')->all();
        $bankQuestions = $this->bankQuestions($request, $selectedBankQuestionIds);
        $questionRows = $activity->questions->whereNull('source_question_id')->map(fn ($question) => [
            'type' => $question->type->value,
            'body' => $question->body,
            'expected_answer' => $question->expected_answer,
            'teacher_instruction' => $question->teacher_instruction,
            'max_score' => $question->max_score,
            'options' => $question->options_snapshot ?? [],
            'correct_options' => collect($question->options_snapshot ?? [])->filter(fn ($option) => (bool) ($option['is_correct'] ?? false))->keys()->all(),
            'rubric' => $question->rubric_snapshot ?? [],
        ])->values()->all();

        return view('teacher.activities.form', compact('activity', 'classrooms', 'bankQuestions', 'questionRows', 'selectedBankQuestionIds', 'selectedBankQuestions', 'metadataOnly'));
    }

    public function update(SaveActivityRequest $request, Activity $activity, SaveActivityDraftAction $save, PublishActivityAction $publish): RedirectResponse
    {
        $this->authorize('update', $activity);
        try {
            $activity = DB::transaction(function () use ($request, $activity, $save, $publish): Activity {
                $activity = $save->execute($activity, $request->user(), $request->validated());

                return $request->input('intent') === 'publish' && $activity->status === ActivityStatus::Draft
                    ? $publish->execute($activity)
                    : $activity;
            });
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['questions' => $e->getMessage()]);
        }
        Cache::forget('dashboard:teacher:'.$request->user()->id);
        if ($request->input('intent') === 'preview') {
            return redirect()->route('teacher.activities.preview', $activity);
        }
        if ($request->input('intent') === 'publish') {
            return redirect()->route('teacher.activities.show', $activity)->with('status', 'Atividade publicada. As perguntas foram congeladas em um snapshot.');
        }

        return back()->with('status', $activity->submissions()->exists() ? 'Dados gerais da atividade atualizados.' : 'Atividade e perguntas atualizadas.');
    }

    public function publish(Request $request, Activity $activity, PublishActivityAction $action): RedirectResponse
    {
        $this->authorize('update', $activity);
        try {
            $action->execute($activity);
        } catch (DomainException $e) {
            return back()->withErrors(['questions' => $e->getMessage()]);
        }
        Cache::forget('dashboard:teacher:'.$request->user()->id);

        return redirect()->route('teacher.activities.show', $activity)->with('status', 'Atividade publicada. O conteudo foi congelado em um snapshot.');
    }

    public function destroy(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('delete', $activity);
        $activity->delete();
        Cache::forget('dashboard:teacher:'.$request->user()->id);

        return redirect()->route('teacher.activities.index')->with('status', 'Atividade e todos os dados relacionados foram excluídos.');
    }

    private function bankQuestions(Request $request, array $selectedIds)
    {
        $search = mb_substr((string) $request->string('bank_q')->trim(), 0, 120);

        $page = max(1, (int) $request->input('bank_page', 1));
        return app(\App\Application\Services\PrivateReadCache::class)->remember($request->user()->id, 'questions', ['picker', $search, $selectedIds, $page], fn () => Question::query()
            ->where('owner_id', $request->user()->id)
            ->where('is_active', true)
            ->when($selectedIds, fn ($query) => $query->whereNotIn('id', $selectedIds))
            ->when($search !== '', fn ($query) => $query->where('body', 'like', '%'.$search.'%'))
            ->latest()
            ->paginate(20, ['id', 'type', 'body', 'max_score'], 'bank_page', $page))
            ->withPath($request->url())->withQueryString();
    }

    private function requestedBankQuestionIds(Request $request, array $fallback = []): array
    {
        if ($request->session()->hasOldInput()) {
            $ids = $request->old('bank_questions', []);
        } elseif ($request->boolean('bank_selection')) {
            $ids = $request->query('bank_questions', []);
        } else {
            return $fallback;
        }
        if (! is_array($ids)) {
            return [];
        }

        return collect($ids)->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)
            ->map(fn ($id) => (int) $id)->unique()->take(50)->values()->all();
    }

    private function selectedBankQuestions(Request $request, ?Activity $activity, array $selectedIds)
    {
        if ($selectedIds === []) {
            return collect();
        }

        $sources = Question::withTrashed()->where('owner_id', $request->user()->id)
            ->whereIn('id', $selectedIds)->get()->keyBy('id');
        $snapshots = $activity?->questions->whereNotNull('source_question_id')->keyBy('source_question_id') ?? collect();

        return collect($selectedIds)->map(function (int $id) use ($sources, $snapshots) {
            $source = $sources->get($id);
            $snapshot = $snapshots->get($id);
            if (! $snapshot && (! $source || $source->trashed() || ! $source->is_active)) {
                return null;
            }

            return [
                'id' => $id,
                'body' => $snapshot?->body ?? $source->body,
                'type' => $snapshot?->type ?? $source->type,
                'max_score' => $snapshot?->max_score ?? $source->max_score,
                'available' => $source && ! $source->trashed() && $source->is_active,
                'import_correction' => $snapshot?->import_correction ?? true,
            ];
        })->filter()->values();
    }
}
