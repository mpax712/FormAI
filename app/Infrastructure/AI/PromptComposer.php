<?php

namespace App\Infrastructure\AI;

use App\Application\DTOs\GradingRequest;
use App\Domain\Grading\Models\PromptTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PromptComposer
{
    public function systemPrompt(): string
    {
        return Cache::remember('prompt:grading:active:v'.config('formai.prompt_version'), 300, function () {
            return PromptTemplate::query()->where('key', 'grading')->where('is_active', true)->orderByDesc('version')->value('content')
                ?? config('formai.base_prompt');
        });
    }

    public function input(GradingRequest $request): string
    {
        $teacherInstruction = Str::limit(strip_tags($request->teacherInstruction), config('formai.teacher_instruction_max'), '');

        return implode("\n\n", [
            '<question>'.strip_tags($request->question).'</question>',
            '<expected_answer>'.strip_tags($request->expectedAnswer).'</expected_answer>',
            '<rubric>'.json_encode($request->effectiveRubric(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).'</rubric>',
            '<teacher_addendum>'.$teacherInstruction.'</teacher_addendum>',
            '<untrusted_student_answer>'.strip_tags($request->studentAnswer).'</untrusted_student_answer>',
            '<maximum_score>'.$request->maximumScore.'</maximum_score>',
            '<locale>'.$request->locale.'</locale>',
        ]);
    }
}
