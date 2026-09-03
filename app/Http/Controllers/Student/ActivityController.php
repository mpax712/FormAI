<?php

namespace App\Http\Controllers\Student;

use App\Application\Actions\SaveAnswerAction;
use App\Application\Actions\SubmitActivityAction;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Models\ActivityQuestion;
use App\Domain\Submissions\Enums\SubmissionStatus;
use App\Domain\Submissions\Models\Submission;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $activities = Activity::query()->whereHas('classroom.students', fn ($q) => $q->whereKey($request->user()->id))
            ->whereIn('status', [ActivityStatus::Published, ActivityStatus::Closed, ActivityStatus::Grading, ActivityStatus::ReviewReady, ActivityStatus::Released])->with('classroom')->with(['submissions' => fn ($q) => $q->where('student_id', $request->user()->id)])->orderBy('deadline_at')->paginate(15);

        return view('student.activities.index', compact('activities'));
    }

    public function show(Request $request, Activity $activity): View
    {
        $this->authorize('view', $activity);
        abort_if($activity->status === ActivityStatus::Draft, 404);
        $submission = Submission::query()->firstOrCreate(['activity_id' => $activity->id, 'student_id' => $request->user()->id], ['status' => SubmissionStatus::Draft, 'version' => 1]);
        $canAnswer = $activity->deadline_at === null
            || $activity->deadline_at->isFuture()
            || ($submission->reopened_until?->isFuture() ?? false);
        abort_if($submission->status === SubmissionStatus::Draft && ! $canAnswer, 403, 'O prazo desta atividade terminou.');
        $activity->load('questions');
        $submission->load('answers');

        return view('student.activities.show', compact('activity', 'submission'));
    }

    public function save(Request $request, Submission $submission, ActivityQuestion $question, SaveAnswerAction $action): JsonResponse
    {
        $this->authorize('update', $submission);
        $data = $request->validate(['response_text' => ['nullable', 'string', 'max:30000'], 'selected_option_key' => ['nullable', 'string', 'max:20'], 'version' => ['required', 'integer', 'min:0']]);
        try {
            $answer = $action->execute($submission, $question, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['saved' => true, 'version' => $answer->version, 'saved_at' => now()->toIso8601String()]);
    }

    public function submit(Submission $submission, SubmitActivityAction $action): RedirectResponse
    {
        $this->authorize('update', $submission);
        try {
            $action->execute($submission);
        } catch (DomainException $e) {
            return back()->withErrors(['submission' => $e->getMessage()]);
        }
        Cache::forget('dashboard:student:'.$submission->student_id);
        Cache::forget('dashboard:teacher:'.$submission->activity->teacher_id);
        return redirect()->route('student.activities.index')->with('status', 'Atividade enviada. Aguarde a publicação do professor.');
    }

    public function result(Submission $submission): View
    {
        $this->authorize('view', $submission);
        abort_unless($submission->status === SubmissionStatus::Released, 404);
        $submission->load(['activity.questions', 'answers.activityQuestion', 'answers.gradingDecision']);

        return view('student.activities.result', compact('submission'));
    }
}
