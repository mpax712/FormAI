<?php

namespace App\Http\Requests\Teacher;

use App\Domain\Activities\Models\Activity;
use App\Domain\QuestionBank\Enums\QuestionType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

class SaveActivityRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $deadline = $this->input('deadline_at');
        if (is_string($deadline)) {
            $deadline = trim($deadline);

            if ($deadline === '') {
                $this->merge(['deadline_at' => null]);
            }

            foreach (['Y-m-d\\TH:i', 'Y-m-d\\TH:i:s', 'Y-m-d H:i:s'] as $format) {
                try {
                    $parsed = CarbonImmutable::createFromFormat(
                        '!'.$format,
                        $deadline,
                        config('app.timezone')
                    );
                } catch (Throwable) {
                    continue;
                }

                if ($parsed !== false && $parsed->format($format) === $deadline) {
                    $this->merge(['deadline_at' => $parsed->format('Y-m-d H:i:s')]);
                    break;
                }
            }
        }

        $questions = $this->input('questions', []);
        if (! is_array($questions)) {
            return;
        }
        foreach ($questions as $questionIndex => $question) {
            if (! is_array($question)) {
                continue;
            }
            $type = $question['type'] ?? null;
            if ($type === QuestionType::Context->value) {
                $questions[$questionIndex]['max_score'] = 0;
                continue;
            }
            if (! in_array($type, [QuestionType::SingleChoice->value, QuestionType::MultipleChoice->value], true)) {
                continue;
            }
            $correctOption = (string) ($question['correct_option'] ?? '');
            $correctOptions = array_map('strval', is_array($question['correct_options'] ?? null) ? $question['correct_options'] : []);
            $options = $question['options'] ?? [];
            if (! is_array($options)) {
                continue;
            }
            foreach ($options as $optionIndex => $option) {
                if (! is_array($option)) {
                    continue;
                }
                $questions[$questionIndex]['options'][$optionIndex]['is_correct'] = $type === QuestionType::SingleChoice->value
                    ? (string) $optionIndex === $correctOption
                    : in_array((string) $optionIndex, $correctOptions, true);
            }
        }
        $this->merge(['questions' => $questions]);
    }

    public function authorize(): bool
    {
        $activity = $this->route('activity');

        return $activity
            ? $this->user()?->can('update', $activity) === true
            : $this->user()?->can('create', Activity::class) === true;
    }

    public function rules(): array
    {
        $activity = $this->route('activity');
        $currentClassroomId = $activity?->classroom_id;
        $metadataOnly = $activity?->submissions()->exists() ?? false;
        $deadlineRules = ['nullable', 'date_format:Y-m-d H:i:s'];
        $submittedDeadline = $this->input('deadline_at');
        $sameDeadlineMinute = ($submittedDeadline === null && $activity?->deadline_at === null)
            || (is_string($submittedDeadline) && substr($submittedDeadline, 0, 16) === $activity?->deadline_at?->format('Y-m-d H:i'));
        if (! $metadataOnly || ! $sameDeadlineMinute) {
            $deadlineRules[] = 'after:now';
        }

        return [
            'classroom_id' => $metadataOnly ? ['required', 'integer', Rule::in([$currentClassroomId])] : ['required', 'integer', Rule::exists('classrooms', 'id')->where(
                fn ($query) => $query->where('teacher_id', $this->user()->id)
                    ->where(function ($available) use ($currentClassroomId): void {
                        $available->where('is_active', true);
                        if ($currentClassroomId) {
                            $available->orWhere('id', $currentClassroomId);
                        }
                    })
            )],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'grading_instructions' => ['nullable', 'string', 'max:2000'],
            'feedback_detail' => ['sometimes', 'required', 'in:short,medium,detailed'],
            'intelligence_profile' => ['sometimes', 'required', 'in:economy,balanced,advanced'],
            'grading_strictness' => ['sometimes', 'required', 'in:flexible,balanced,strict'],
            'deadline_at' => $deadlineRules,
            'intent' => ['nullable', Rule::in(['draft', 'preview', 'publish', 'metadata'])],

            'bank_questions' => $metadataOnly ? ['prohibited'] : ['nullable', 'array', 'max:50'],
            'bank_correction' => ['nullable', 'array', 'max:100'],
            'bank_correction.*' => ['boolean'],
            'bank_questions.*' => ['integer', 'distinct', Rule::exists('questions', 'id')->where(
                fn ($query) => $query->where('owner_id', $this->user()->id)->where('is_active', true)->whereNull('deleted_at')
            )],

            'questions' => $metadataOnly ? ['prohibited'] : ['nullable', 'array', 'max:50'],
            'questions.*' => ['array'],
            'questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            'questions.*.body' => ['required', 'string', 'max:10000'],
            'questions.*.expected_answer' => ['nullable', 'string', 'max:10000'],
            'questions.*.teacher_instruction' => ['prohibited'],
            'questions.*.max_score' => ['required', 'numeric', 'min:0', 'max:1000'],
            'questions.*.correct_option' => ['nullable', 'integer', 'min:0', 'max:9'],
            'questions.*.correct_options' => ['nullable', 'array', 'max:10'],
            'questions.*.correct_options.*' => ['integer', 'min:0', 'max:9', 'distinct'],
            'questions.*.options' => ['nullable', 'array', 'max:10'],
            'questions.*.options.*' => ['array'],
            'questions.*.options.*.text' => ['nullable', 'string', 'max:2000'],
            'questions.*.options.*.is_correct' => ['sometimes', 'boolean'],
            'questions.*.rubric' => ['nullable', 'array', 'max:10'],
            'questions.*.rubric.*' => ['array'],
            'questions.*.rubric.*.label' => ['nullable', 'string', 'max:120'],
            'questions.*.rubric.*.description' => ['nullable', 'string', 'max:3000'],
            'questions.*.rubric.*.weight' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $activity = $this->route('activity');
            if ($activity?->submissions()->exists()) {
                return;
            }

            $directInput = $this->input('questions', []);
            $bankInput = $this->input('bank_questions', []);
            $directQuestions = is_array($directInput) ? $directInput : [];
            $bankQuestions = is_array($bankInput) ? array_filter($bankInput) : [];

            if (count(array_filter($directQuestions, fn ($question) => is_array($question) && ($question['type'] ?? null) !== QuestionType::Context->value)) + count($bankQuestions) === 0) {
                $validator->errors()->add('questions', 'Adicione ao menos uma questão com pontuação além dos blocos de contexto.');
            }
            if (count($directQuestions) + count($bankQuestions) > 50) {
                $validator->errors()->add('questions', 'A atividade pode ter no máximo 50 perguntas no total.');
            }

            foreach ($directQuestions as $index => $question) {
                if (! is_array($question)) {
                    continue;
                }
                $type = $question['type'] ?? null;

                if ($type !== QuestionType::Context->value && (float) ($question['max_score'] ?? 0) <= 0) {
                    $validator->errors()->add("questions.$index.max_score", 'A pontuação da questão deve ser maior que zero.');
                }

                if (in_array($type, [QuestionType::SingleChoice->value, QuestionType::MultipleChoice->value], true)) {
                    $optionInput = $question['options'] ?? [];
                    $options = collect(is_array($optionInput) ? $optionInput : [])->filter(
                        fn ($option) => is_array($option) && filled($option['text'] ?? null)
                    );
                    if ($options->count() < 2) {
                        $validator->errors()->add("questions.$index.options", 'Preencha ao menos duas alternativas.');
                    }
                    $correct = $options->filter(fn ($option) => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOL))->count();
                    if ($type === QuestionType::SingleChoice->value && $correct !== 1) {
                        $validator->errors()->add("questions.$index.options", 'Marque exatamente uma alternativa correta.');
                    }
                    if ($type === QuestionType::MultipleChoice->value && $correct < 2) {
                        $validator->errors()->add("questions.$index.options", 'Marque pelo menos duas alternativas corretas na múltipla escolha.');
                    }
                }

                if ($type === QuestionType::Essay->value) {
                    $rubricInput = $question['rubric'] ?? [];
                    $criteria = collect(is_array($rubricInput) ? $rubricInput : [])->filter(
                        fn ($criterion) => is_array($criterion) && (
                            filled($criterion['label'] ?? null)
                            || filled($criterion['description'] ?? null)
                            || filled($criterion['weight'] ?? null)
                        )
                    );

                    foreach ($criteria as $criterionIndex => $criterion) {
                        if (! filled($criterion['label'] ?? null)) {
                            $validator->errors()->add("questions.$index.rubric.$criterionIndex.label", 'Informe o nome de cada critério preenchido.');
                        }
                        if (! is_numeric($criterion['weight'] ?? null) || (float) $criterion['weight'] <= 0) {
                            $validator->errors()->add("questions.$index.rubric.$criterionIndex.weight", 'Informe um peso maior que zero para cada critério preenchido.');
                        }
                    }

                    $labels = $criteria->pluck('label')->filter()->map(fn ($label) => mb_strtolower(trim((string) $label)));
                    if ($labels->duplicates()->isNotEmpty()) {
                        $validator->errors()->add("questions.$index.rubric", 'Os critérios de uma questão devem ter nomes diferentes.');
                    }
                    $maximumScore = (float) ($question['max_score'] ?? 0);
                    if ($criteria->isNotEmpty() && abs($criteria->sum(fn ($criterion) => (float) ($criterion['weight'] ?? 0)) - $maximumScore) > 0.001) {
                        $validator->errors()->add("questions.$index.rubric", 'A soma dos pontos dos critérios deve ser igual à pontuação da questão.');
                    }
                }
            }
        }];
    }
}
