<?php

namespace App\Application\Actions;

use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\QuestionBank\Models\Question;
use DomainException;
use Illuminate\Support\Facades\DB;

class PublishActivityAction
{
    public function execute(Activity $activity): Activity
    {
        return DB::transaction(function () use ($activity) {
            $locked = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            $locked->ensureDraft();
            $classroom = Classroom::query()->whereKey($locked->classroom_id)
                ->where('teacher_id', $locked->teacher_id)->where('is_active', true)->lockForUpdate()->first();
            if (! $classroom) {
                throw new DomainException('Reative a turma antes de publicar esta atividade.');
            }
            if ($locked->deadline_at && ! $locked->deadline_at->isFuture()) {
                throw new DomainException('Defina um prazo futuro antes de publicar esta atividade.');
            }
            $questions = $locked->questions()->get();
            if ($questions->isEmpty()) {
                throw new DomainException('Adicione ao menos uma pergunta antes de publicar.');
            }

            $sourceIds = $questions->pluck('source_question_id')->filter()->unique()->values();
            $sources = Question::query()
                ->where('owner_id', $locked->teacher_id)
                ->where('is_active', true)
                ->whereIn('id', $sourceIds)
                ->lockForUpdate()
                ->with(['options', 'rubricCriteria'])
                ->get()
                ->keyBy('id');
            if ($sources->count() !== $sourceIds->count()) {
                throw new DomainException('Uma pergunta importada do banco não está mais disponível. Remova-a ou selecione outra.');
            }
            foreach ($questions->whereNotNull('source_question_id') as $snapshot) {
                $source = $sources->get($snapshot->source_question_id);
                $snapshot->update([
                    'type' => $source->type,
                    'body' => $source->body,
                    'expected_answer' => $source->expected_answer,
                    'teacher_instruction' => null,
                    'max_score' => $source->max_score,
                    'options_snapshot' => $source->options->map(fn ($option) => ['key' => $option->option_key, 'text' => $option->text, 'is_correct' => $option->is_correct])->values()->all(),
                    'rubric_snapshot' => $source->rubricCriteria->map(fn ($criterion) => ['label' => $criterion->label, 'description' => $criterion->description, 'weight' => (float) $criterion->weight])->values()->all(),
                ]);
            }
            $questions = $locked->questions()->get();

            $locked->update([
                'status' => ActivityStatus::Published,
                'published_at' => now(),
                'total_score' => $questions->sum(fn ($question) => (float) $question->max_score),
            ]);

            return $locked->fresh('questions');
        });
    }
}
