<?php

namespace App\Application\Actions;

use App\Domain\Activities\Models\ActivityQuestion;
use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\Submissions\Enums\SubmissionStatus;
use App\Domain\Submissions\Models\Answer;
use App\Domain\Submissions\Models\Submission;
use DomainException;
use Illuminate\Support\Facades\DB;

class SaveAnswerAction
{
    public function execute(Submission $submission, ActivityQuestion $question, array $data): Answer
    {
        return DB::transaction(function () use ($submission, $question, $data) {
            $locked = Submission::query()->lockForUpdate()->findOrFail($submission->id);
            if ($locked->status !== SubmissionStatus::Draft) {
                throw new DomainException('Esta entrega nao aceita mais alteracoes.');
            }
            $hasExtension = $locked->reopened_until?->isFuture() ?? false;
            if ($locked->activity_id !== $question->activity_id || ($locked->activity->deadline_at && now()->greaterThan($locked->activity->deadline_at) && ! $hasExtension)) {
                throw new DomainException('A atividade foi encerrada.');
            }

            if ($question->type === QuestionType::SingleChoice && filled($data['selected_option_key'] ?? null)) {
                $selectedKey = (string) $data['selected_option_key'];
                $isValidOption = collect($question->options_snapshot ?? [])
                    ->contains(fn ($option) => is_array($option) && (string) ($option['key'] ?? '') === $selectedKey);
                if (! $isValidOption) {
                    throw new DomainException('A alternativa selecionada não pertence a esta questão.');
                }
            }

            $answer = Answer::query()->where('submission_id', $locked->id)->where('activity_question_id', $question->id)->lockForUpdate()->first();
            $clientVersion = (int) ($data['version'] ?? 0);
            if ($answer && $clientVersion !== $answer->version) {
                throw new DomainException('A resposta foi atualizada em outra aba. Recarregue a pagina.');
            }

            $values = [
                'response_text' => $question->type === QuestionType::Essay ? ($data['response_text'] ?? null) : null,
                'selected_option_key' => $question->type === QuestionType::SingleChoice ? ($data['selected_option_key'] ?? null) : null,
                'version' => ($answer?->version ?? 0) + 1,
            ];

            if ($answer) {
                $answer->update($values);
            } else {
                $answer = $locked->answers()->create($values + ['activity_question_id' => $question->id]);
            }
            $locked->increment('version');

            return $answer->fresh();
        });
    }
}
