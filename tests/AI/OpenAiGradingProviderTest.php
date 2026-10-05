<?php

namespace Tests\AI;

use App\Application\DTOs\GradingRequest;
use App\Infrastructure\AI\OpenAiGradingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiGradingProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_structured_stateless_anonymized_request(): void
    {
        config(['services.openai.key' => 'test-key', 'services.openai.base_url' => 'https://api.openai.test/v1']);
        Http::fake(['api.openai.test/*' => Http::response([
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'score' => 8.5, 'criterion_scores' => [['criterion' => 'Clareza', 'score' => 8.5, 'justification' => 'Correto']],
                'evidence' => ['Trecho relevante'], 'feedback' => 'Boa resposta.', 'confidence' => .9, 'warnings' => [],
            ])]]]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 40],
        ], 200)]);

        $result = app(OpenAiGradingProvider::class)->grade(new GradingRequest('Pergunta', 'Esperado', [['label' => 'Clareza', 'weight' => 10]], 'Resposta sem nome', 'Seja objetivo', 10, 1, 'pt-BR', str_repeat('a', 64), str_repeat('b', 64)));
        $this->assertSame(8.5, $result->score);
        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $request->url() === 'https://api.openai.test/v1/responses'
                && $data['store'] === false
                && $data['model'] === 'gpt-5.6-terra'
                && $data['text']['format']['type'] === 'json_schema'
                && ! str_contains($data['input'], '@');
        });
    }

    public function test_it_uses_a_default_criterion_when_the_question_has_no_rubric(): void
    {
        config(['services.openai.key' => 'test-key', 'services.openai.base_url' => 'https://api.openai.test/v1']);
        Http::fake(['api.openai.test/*' => Http::response([
            'output_text' => json_encode([
                'score' => 7,
                'criterion_scores' => [[
                    'criterion' => 'Qualidade geral da resposta',
                    'score' => 7,
                    'justification' => 'Resposta relevante e clara.',
                ]],
                'evidence' => ['Trecho relevante'],
                'feedback' => 'Desenvolva um pouco mais a conclusão.',
                'confidence' => .8,
                'warnings' => [],
            ]),
            'usage' => ['input_tokens' => 80, 'output_tokens' => 30],
        ], 200)]);

        $result = app(OpenAiGradingProvider::class)->grade(new GradingRequest(
            'Explique o tema.',
            '',
            [],
            'Resposta do aluno.',
            '',
            10,
            1,
            'pt-BR',
            str_repeat('a', 64),
            str_repeat('b', 64),
        ));

        $this->assertSame(7.0, $result->score);
        Http::assertSent(fn (Request $request) => str_contains($request->data()['input'], 'Qualidade geral da resposta'));
    }
}
