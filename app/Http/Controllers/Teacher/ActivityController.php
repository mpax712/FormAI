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
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $activities = Activity::query()->where('teacher_id', $request->user()->id)->with('classroom:id,public_id,name')->withCount('submissions')->latest()->paginate(15);
        return view('teacher.activities.index', compact('activities'));
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
        return view('teacher.activities.show', compact('activity', 'aiConfigured', 'aiKeyName'));
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
        try { $activity->ensureDraft(); } catch (DomainException $e) { abort(409, $e->getMessage()); }
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
            'rubric' => $question->rubric_snapshot ?? [],
        ])->values()->all();
        return view('teacher.activities.form', compact('activity', 'classrooms', 'bankQuestions', 'questionRows', 'selectedBankQuestionIds', 'selectedBankQuestions'));
    }
    public function update(SaveActivityRequest $request, Activity $activity, SaveActivityDraftAction $save, PublishActivityAction $publish): RedirectResponse
    {
        $this->authorize('update', $activity);
        try {
            $activity = DB::transaction(function () use ($request, $activity, $save, $publish): Activity {
                $activity = $save->execute($activity, $request->user(), $request->validated());
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
            return redirect()->route('teacher.activities.show', $activity)->with('status', 'Atividade publicada. As perguntas foram congeladas em um snapshot.');
        }
        return back()->with('status', 'Rascunho e perguntas atualizados.');
    }
    public function publish(Request $request, Activity $activity, PublishActivityAction $action): RedirectResponse
    {
        $this->authorize('update', $activity);
        try { $action->execute($activity); } catch (DomainException $e) { return back()->withErrors(['questions' => $e->getMessage()]); }
        Cache::forget('dashboard:teacher:'.$request->user()->id);
        return redirect()->route('teacher.activities.show', $activity)->with('status', 'Atividade publicada. O conteudo foi congelado em um snapshot.');
    }
    private function bankQuestions(Request $request, array $selectedIds)
    {
        $search = mb_substr((string) $request->string('bank_q')->trim(), 0, 120);
        return Question::query()
            ->where('owner_id', $request->user()->id)
            ->where('is_active', true)
            ->when($selectedIds, fn ($query) => $query->whereNotIn('id', $selectedIds))
            ->when($search !== '', fn ($query) => $query->where('body', 'like', '%'.$search.'%'))
            ->latest()
            ->paginate(20, ['id', 'type', 'body', 'max_score'], 'bank_page')
            ->withQueryString();
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
        if (! is_array($ids)) return [];
        return collect($ids)->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)
            ->map(fn ($id) => (int) $id)->unique()->take(50)->values()->all();
    }

    private function selectedBankQuestions(Request $request, ?Activity $activity, array $selectedIds)
    {
        if ($selectedIds === []) return collect();

        $sources = Question::withTrashed()->where('owner_id', $request->user()->id)
            ->whereIn('id', $selectedIds)->get()->keyBy('id');
        $snapshots = $activity?->questions->whereNotNull('source_question_id')->keyBy('source_question_id') ?? collect();

        return collect($selectedIds)->map(function (int $id) use ($sources, $snapshots) {
            $source = $sources->get($id);
            $snapshot = $snapshots->get($id);
            if (! $snapshot && (! $source || $source->trashed() || ! $source->is_active)) return null;
            return [
                'id' => $id,
                'body' => $snapshot?->body ?? $source->body,
                'type' => $snapshot?->type ?? $source->type,
                'max_score' => $snapshot?->max_score ?? $source->max_score,
                'available' => $source && ! $source->trashed() && $source->is_active,
            ];
        })->filter()->values();
    }
}
