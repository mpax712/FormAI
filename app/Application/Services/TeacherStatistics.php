<?php

namespace App\Application\Services;

use App\Domain\Activities\Models\Activity;
use App\Domain\Grading\Models\GradingRun;
use App\Domain\Submissions\Models\Answer;
use App\Domain\Submissions\Models\Submission;
use Illuminate\Support\Facades\Cache;

class TeacherStatistics
{
    public function summary(int $teacherId, array $filters): array
    {
        $filters['page'] = 1;
        return Cache::store(config('cache.default') === 'database' ? 'file' : config('cache.default'))
            ->remember('private:teacher:'.$teacherId.':summary:v1:'.hash('sha256', json_encode($filters)), 30, function () use ($teacherId, $filters) {
                $activities = Activity::where('teacher_id', $teacherId)->where('status', '!=', 'draft')
                    ->when($filters['classroom'] ?? null, fn ($q, $id) => $q->whereHas('classroom', fn ($q) => $q->where('public_id', $id)))
                    ->when(in_array($filters['period'] ?? 'all', ['30', '90']), fn ($q) => $q->where('published_at', '>=', now()->subDays((int) $filters['period'])->startOfDay()))
                    ->when(($filters['period'] ?? '') === 'custom', fn ($q) => $q->whereBetween('published_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59']));
                $ids = (clone $activities)->select('id');
                return [
                    'activities' => (clone $activities)->count(),
                    'awaiting_review' => Submission::whereIn('activity_id', $ids)->whereIn('status', ['submitted', 'processing'])->count(),
                    'published' => Submission::whereIn('activity_id', $ids)->where('status', 'released')->count(),
                ];
            });
    }

    public function get(int $teacherId, array $filters = []): array
    {
        // Only aggregate data and activity titles; never answers, credentials or student identities.
        return Cache::store(config('cache.default') === 'database' ? 'file' : config('cache.default'))
            ->remember('private:teacher:'.$teacherId.':statistics:v2:'.hash('sha256', json_encode($filters)), 30, function () use ($teacherId, $filters) {
                $activities = Activity::where('teacher_id', $teacherId)
                    ->where('status', '!=', 'draft')
                    ->when($filters['classroom'] ?? null, fn ($q, $id) => $q->whereHas('classroom', fn ($q) => $q->where('public_id', $id)))
                    ->when(in_array($filters['period'] ?? 'all', ['30', '90']), fn ($q) => $q->where('published_at', '>=', now()->subDays((int) $filters['period'])->startOfDay()))
                    ->when(($filters['period'] ?? '') === 'custom', fn ($q) => $q->whereBetween('published_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59']))
                    ->withAvg(['submissions as published_average' => fn ($q) => $q->where('status', 'released')->whereNotNull('final_score')], 'final_score')
                    ->with(['classroom' => fn ($q) => $q->select('id', 'public_id', 'name')->withCount('students')])
                    ->withCount([
                        'submissions as delivered' => fn ($q) => $q->whereNotNull('submitted_at')->whereHas('student.classrooms', fn ($q) => $q->whereColumn('classrooms.id', 'activities.classroom_id')),
                        'submissions as published_count' => fn ($q) => $q->where('status', 'released'),
                        'submissions as published_samples' => fn ($q) => $q->where('status', 'released')->whereNotNull('final_score'),
                        'submissions as reviewed' => fn ($q) => $q->whereIn('status', ['reviewed', 'released']),
                        'submissions as awaiting_review' => fn ($q) => $q->whereIn('status', ['submitted', 'processing']),
                    ])->latest()->get()->map(fn ($activity) => [
                        'id' => $activity->public_id, 'title' => $activity->title,
                        'classroom' => $activity->classroom->name, 'classroom_id' => $activity->classroom->public_id,
                        'published_count' => $activity->published_count,
                        'sample_count' => $activity->total_score > 0 ? $activity->published_samples : 0,
                        'valid_maximum' => $activity->total_score > 0,
                        'average_percent' => $activity->total_score > 0 && $activity->published_average !== null ? round($activity->published_average / $activity->total_score * 100, 1) : null,
                        'delivered' => $activity->delivered,
                        'pending' => max(0, $activity->classroom->students_count - $activity->delivered),
                        'reviewed' => $activity->reviewed, 'awaiting_review' => $activity->awaiting_review,
                    ])->all();
                $runs = GradingRun::whereHas('answer.submission.activity', fn ($q) => $q->where('teacher_id', $teacherId));
                $tokens = (clone $runs)->selectRaw('COALESCE(SUM(input_tokens), 0) as input_tokens, COALESCE(SUM(output_tokens), 0) as output_tokens')->first();
                $statuses = (clone $runs)->whereIn('id', (clone $runs)->selectRaw('MAX(id)')->groupBy('answer_id'))
                    ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all();
                $statuses['not_requested'] = Answer::whereHas('submission', fn ($q) => $q
                    ->whereIn('status', ['submitted', 'processing'])
                    ->whereHas('activity', fn ($q) => $q->where('teacher_id', $teacherId)))
                    ->whereHas('activityQuestion', fn ($q) => $q->where('type', 'essay'))
                    ->whereDoesntHave('gradingRuns')->count();

                $summary = ['activities' => count($activities), 'awaiting_review' => array_sum(array_column($activities, 'awaiting_review')), 'published' => array_sum(array_column($activities, 'published_count'))];
                $total = count($activities);
                if (isset($filters['page'])) $activities = array_slice($activities, (max(1, (int) $filters['page']) - 1) * 10, 10);
                return ['summary' => $summary, 'pagination' => ['total' => $total, 'per_page' => 10, 'page' => (int) ($filters['page'] ?? 1)], 'activities' => $activities, 'ai' => $statuses, 'tokens' => ['input' => (int) $tokens->input_tokens, 'output' => (int) $tokens->output_tokens]];
            });
    }
}
