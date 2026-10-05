<?php

namespace Tests\AI;

use App\Application\DTOs\GradingRequest;
use App\Domain\Grading\Contracts\AiGradingProvider;
use App\Infrastructure\AI\Exceptions\RetryableAiException;
use App\Infrastructure\AI\GeminiGradingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiGradingProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_gemini_generate_content_with_structured_output(): void
    {
        config([
            'formai.ai_provider' => 'gemini',
            'services.gemini.key' => 'gemini-test-key',
            'services.gemini.base_url' => 'https://gemini.test/v1beta',
            'services.gemini.model' => 'gemini-test-model',
        ]);
        Http::fake(['gemini.test/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => json_encode([
                                'score' => 8.5,
                                'criterion_scores' => [['criterion' => 'Clareza', 'score' => 8.5, 'justification' => 'Resposta clara.']],
                                'evidence' => ['Trecho relevante'],
                                'feedback' => 'Boa resposta.',
                                'confidence' => .9,
                                'warnings' => [],
                            ])],
                        ],
                    ],
                ],
            ],
            'usageMetadata' => ['promptTokenCount' => 110, 'candidatesTokenCount' => 45],
        ], 200)]);

        $provider = app(AiGradingProvider::class);
        $this->assertInstanceOf(GeminiGradingProvider::class, $provider);
        $result = $provider->grade($this->request());

        $this->assertSame(8.5, $result->score);
        $this->assertSame(110, $result->inputTokens);
        $this->assertSame(45, $result->outputTokens);
        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $request->url() === 'https://gemini.test/v1beta/models/gemini-test-model:generateContent'
                && $request->hasHeader('x-goog-api-key', 'gemini-test-key')
                && $data['generationConfig']['responseMimeType'] === 'application/json'
                && $data['generationConfig']['responseJsonSchema']['type'] === 'object'
                && ! str_contains($data['contents'][0]['parts'][0]['text'], '@');
        });
    }

    public function test_server_errors_are_retryable(): void
    {
        config([
            'services.gemini.key' => 'gemini-test-key',
            'services.gemini.base_url' => 'https://gemini.test/v1beta',
            'services.gemini.model' => 'gemini-test-model',
        ]);
        Http::fake(['gemini.test/*' => Http::response([], 500)]);

        $this->expectException(RetryableAiException::class);
        $this->expectExceptionMessage('HTTP 500');

        (new GeminiGradingProvider)->grade($this->request());
    }

    private function request(): GradingRequest
    {
        return new GradingRequest(
            'Pergunta',
            'Resposta esperada',
            [['label' => 'Clareza', 'weight' => 10]],
            'Resposta sem identificação',
            'Seja objetivo',
            10,
            1,
            'pt-BR',
            str_repeat('a', 64),
            str_repeat('b', 64),
        );
    }
}
