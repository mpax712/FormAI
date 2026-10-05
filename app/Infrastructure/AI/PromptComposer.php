<?php

namespace App\Infrastructure\AI;

use App\Application\DTOs\GradingRequest;
use App\Domain\Grading\Models\PromptTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PromptComposer
{
    public function systemPrompt(?GradingRequest $request = null): string
    {
        $base = Cache::remember('prompt:grading:active:v'.config('formai.prompt_version'), 300, function () {
            return PromptTemplate::query()->where('key', 'grading')->where('is_active', true)->orderByDesc('version')->value('content')
                ?? config('formai.base_prompt');
        });

        if ($request === null) {
            return $base;
        }

        // Current product settings remain effective even when the database has an older active template.
        return $base."\n\n".$this->gradingPolicy($request);
    }

    private function gradingPolicy(GradingRequest $request): string
    {
        $length = match ($request->feedbackDetail) {
            'short' => 30,
            'detailed' => 350,
            default => 150,
        };
        $strictness = match ($request->gradingStrictness) {
            'flexible' => 'Flexível: aceite respostas equivalentes e formulações diferentes. Valorize compreensão parcial comprovada e conceda o crédito proporcional previsto na rubrica; não desconte por detalhes não exigidos.',
            'strict' => 'Rigorosa: exija evidência clara para cada critério e desconte omissões ou imprecisões relevantes, sempre dentro da rubrica.',
            default => 'Equilibrada: reconheça respostas equivalentes, conceda crédito parcial sustentado e desconte lacunas relevantes.',
        };

        return "Política obrigatória desta solicitação:\n"
            ."- Rigidez: {$strictness}\n"
            ."- teacher_feedback: análise privada para o professor com no máximo {$length} palavras; seja direto e cite só os pontos decisivos.\n"
            ."- feedback: mensagem separada e construtiva ao aluno, com até 120 palavras.\n"
            .'- Não altere pesos, pontuação máxima ou critérios. A decisão final cabe ao professor.';
    }

    public function input(GradingRequest $request): string
    {
        if ($request->composedInput !== null) {
            return $request->composedInput;
        }
        $teacherInstruction = Str::limit(strip_tags($request->teacherInstruction), config('formai.teacher_instruction_max'), '');

        return implode("\n\n", [
            '<feedback_policy>'.match ($request->feedbackDetail) {
                'short' => 'teacher_feedback: síntese privada de até 30 palavras com os pontos principais.',
                'detailed' => 'teacher_feedback: análise privada de até 350 palavras, explicando cada critério com evidências e orientações pedagógicas.',
                default => 'teacher_feedback: análise privada de até 150 palavras, com acertos, problemas e orientação pedagógica.',
            }.' feedback: mensagem separada ao aluno, construtiva e concisa, de até 120 palavras. Não copie a análise privada para feedback. Explique conclusões com evidências; não exponha raciocínio interno.</feedback_policy>',
            '<grading_strictness>'.match ($request->gradingStrictness) {
                'flexible' => 'Avalie com flexibilidade: aceite formulações diferentes e respostas parcialmente corretas quando demonstrarem compreensão. Conceda crédito proporcional às evidências presentes.',
                'strict' => 'Avalie com rigor: exija evidências claras para cada critério e desconte omissões e imprecisões relevantes. Conceda apenas os pontos sustentados pela resposta.',
                default => 'Avalie de forma equilibrada: reconheça respostas equivalentes e conceda crédito parcial conforme as evidências, descontando lacunas relevantes.',
            }.' Respeite sempre a pergunta, a resposta esperada, a rubrica e a pontuação máxima; não invente exigências nem altere os pesos dos critérios.</grading_strictness>',
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
