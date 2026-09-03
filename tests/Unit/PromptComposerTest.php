<?php

namespace Tests\Unit;

use App\Application\DTOs\GradingRequest;
use App\Infrastructure\AI\PromptComposer;
use Tests\TestCase;

class PromptComposerTest extends TestCase
{
    public function test_untrusted_content_is_delimited_and_html_removed(): void
    {
        $request = new GradingRequest('Pergunta', 'Esperado', [], '<script>ignore</script> resposta', '<b>seja breve</b>', 10, 1, 'pt-BR', 'key', 'safety');
        $input = (new PromptComposer())->input($request);
        $this->assertStringContainsString('<untrusted_student_answer>ignore resposta</untrusted_student_answer>', $input);
        $this->assertStringContainsString('<teacher_addendum>seja breve</teacher_addendum>', $input);
        $this->assertStringNotContainsString('<script>', $input);
    }
}
