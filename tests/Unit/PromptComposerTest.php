<?php

namespace Tests\Unit;

use App\Application\DTOs\GradingRequest;
use App\Domain\Grading\Models\PromptTemplate;
use App\Infrastructure\AI\PromptComposer;
use App\Infrastructure\AI\GradingResponseMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PromptComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_untrusted_content_is_delimited_and_html_removed(): void
    {
        $request = new GradingRequest('Pergunta', 'Esperado', [], '<script>ignore</script> resposta', '<b>seja breve</b>', 10, 1, 'pt-BR', 'key', 'safety');
        $input = (new PromptComposer())->input($request);
        $this->assertStringContainsString('<untrusted_student_answer>ignore resposta</untrusted_student_answer>', $input);
        $this->assertStringContainsString('<teacher_addendum>seja breve</teacher_addendum>', $input);
        $this->assertStringNotContainsString('<script>', $input);
    }

    public function test_grading_strictness_changes_instructions_without_changing_rubric(): void
    {
        $composer = new PromptComposer();
        $rubric = [['label' => 'Argumentação', 'description' => 'Apresenta evidências.', 'weight' => 10]];
        $base = ['question' => 'Explique.', 'expectedAnswer' => 'Resposta.', 'rubric' => $rubric,
            'studentAnswer' => 'Resposta parcial.', 'teacherInstruction' => '', 'maximumScore' => 10,
            'promptVersion' => 1, 'locale' => 'pt-BR', 'idempotencyKey' => 'key', 'safetyIdentifier' => 'safety'];

        $flexible = $composer->input(new GradingRequest(...$base, gradingStrictness: 'flexible'));
        $strict = $composer->input(new GradingRequest(...$base, gradingStrictness: 'strict'));

        $this->assertStringContainsString('Conceda crédito proporcional', $flexible);
        $this->assertStringContainsString('exija evidências claras', $strict);
        $this->assertStringContainsString('"weight":10', $flexible);
        $this->assertStringContainsString('"weight":10', $strict);
        $this->assertNotSame($flexible, $strict);
    }

    public function test_current_policy_applies_even_when_an_old_database_template_is_active(): void
    {
        PromptTemplate::query()->create(['key' => 'grading', 'version' => 1, 'content' => 'Modelo antigo em uso.', 'is_active' => true]);
        Cache::flush();
        $request = new GradingRequest('Pergunta', 'Resposta', [], 'Resposta parcial', '', 10, 2, 'pt-BR', 'key', 'safety', feedbackDetail: 'short', gradingStrictness: 'flexible');

        $system = (new PromptComposer)->systemPrompt($request);

        $this->assertStringContainsString('Modelo antigo em uso.', $system);
        $this->assertStringContainsString('Flexível: aceite respostas equivalentes', $system);
        $this->assertStringContainsString('no máximo 30 palavras', $system);
    }

    public function test_private_analysis_short_limit_is_applied_to_the_saved_result(): void
    {
        $request = new GradingRequest('Pergunta', 'Resposta', [], 'Resposta', '', 10, 2, 'pt-BR', 'key', 'safety', feedbackDetail: 'short');
        $result = (new GradingResponseMapper)->map([
            'score' => 8,
            'criterion_scores' => [['criterion' => 'Qualidade geral da resposta', 'score' => 8, 'justification' => 'Compreensão demonstrada.']],
            'evidence' => ['Resposta'], 'feedback' => 'Feedback ao aluno sem cortes.',
            'teacher_feedback' => implode(' ', array_fill(0, 45, 'palavra')),
            'confidence' => .8, 'warnings' => [],
        ], $request, null, null);

        $this->assertCount(30, preg_split('/\s+/u', $result->teacherFeedback));
        $this->assertSame('Feedback ao aluno sem cortes.', $result->feedback);
    }
}
