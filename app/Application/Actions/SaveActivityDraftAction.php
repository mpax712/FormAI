<?php

namespace App\Application\Actions;

use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\Question;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveActivityDraftAction
{
    public function execute(?Activity $activity, User $teacher, array $data): Activity
    {
        return DB::transaction(function () use ($activity, $teacher, $data): Activity {
            if ($activity) {
                $activity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
                $activity->ensureDraft();
                if ($activity->teacher_id !== $teacher->id) {
                    throw new DomainException('A atividade não pertence a este professor.');
                }
            }

            $classroom = Classroom::query()
                ->where('teacher_id', $teacher->id)
                ->where(function ($available) use ($activity): void {
                    $available->where('is_active', true);
                    if ($activity) {
                        $available->orWhere('id', $activity->classroom_id);
                    }
                })
                ->whereKey($data['classroom_id'])
                ->lockForUpdate()
                ->first();
            if (! $classroom) {
                throw new DomainException('A turma selecionada não está disponível para este professor.');
            }

            if ($activity) {
                $activity->update(Arr::only($data, ['classroom_id', 'title', 'description', 'deadline_at']));
            } else {
                $activity = $classroom->activities()->create(Arr::only($data, ['title', 'description', 'deadline_at']) + [
                    'teacher_id' => $teacher->id,
                    'status' => ActivityStatus::Draft,
                ]);
            }

            $bankIds = array_values(array_unique($data['bank_questions'] ?? []));
            if (count($bankIds) + count($data['questions'] ?? []) > 50) {
                throw new DomainException('A atividade pode ter no máximo 50 perguntas no total.');
            }
            $bankQuestions = Question::query()
                ->where('owner_id', $teacher->id)
                ->where('is_active', true)
                ->whereIn('id', $bankIds)
                ->with(['options', 'rubricCriteria'])
                ->get()
                ->keyBy('id');

            if ($bankQuestions->count() !== count($bankIds)) {
                throw new DomainException('Uma ou mais perguntas do banco não estão disponíveis para este professor.');
            }

            $activity->questions()->delete();
            $position = 1;

            foreach ($bankIds as $bankId) {
                $question = $bankQuestions->get($bankId);
                $activity->questions()->create([
                    'source_question_id' => $question->id,
                    'type' => $question->type,
                    'body' => $question->body,
                    'expected_answer' => $question->expected_answer,
                    'teacher_instruction' => null,
                    'max_score' => $question->max_score,
                    'options_snapshot' => $question->options->map(fn ($option) => ['key' => $option->option_key, 'text' => $option->text, 'is_correct' => $option->is_correct])->values()->all(),
                    'rubric_snapshot' => $question->rubricCriteria->map(fn ($criterion) => ['label' => $criterion->label, 'description' => $criterion->description, 'weight' => (float) $criterion->weight])->values()->all(),
                    'position' => $position++,
                ]);
            }

            foreach ($data['questions'] ?? [] as $payload) {
                $type = QuestionType::from($payload['type']);
                $options = $type === QuestionType::SingleChoice
                    ? collect($payload['options'] ?? [])->filter(fn ($option) => filled($option['text'] ?? null))->values()->map(fn ($option, $index) => [
                        'key' => chr(65 + $index),
                        'text' => trim($option['text']),
                        'is_correct' => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOL),
                    ])->all()
                    : [];
                $rubric = $type === QuestionType::Essay
                    ? collect($payload['rubric'] ?? [])->filter(fn ($criterion) => filled($criterion['label'] ?? null))->values()->map(fn ($criterion) => [
                        'label' => trim($criterion['label']),
                        'description' => trim((string) ($criterion['description'] ?? '')),
                        'weight' => (float) ($criterion['weight'] ?? 0),
                    ])->all()
                    : [];

                $activity->questions()->create([
                    'source_question_id' => null,
                    'type' => $type,
                    'body' => trim($payload['body']),
                    'expected_answer' => $type === QuestionType::Essay && filled($payload['expected_answer'] ?? null)
                        ? trim($payload['expected_answer'])
                        : null,
                    'teacher_instruction' => null,
                    'max_score' => $payload['max_score'],
                    'options_snapshot' => $options,
                    'rubric_snapshot' => $rubric,
                    'position' => $position++,
                ]);
            }

            $activity->update(['total_score' => $activity->questions()->sum('max_score')]);

            return $activity->fresh(['classroom', 'questions']);
        });
    }
}
