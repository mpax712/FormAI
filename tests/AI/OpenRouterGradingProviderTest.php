<?php

namespace Tests\AI;

use App\Application\DTOs\GradingRequest;
use App\Domain\Grading\Contracts\AiGradingProvider;
use App\Infrastructure\AI\AiProviderConfiguration;
use App\Infrastructure\AI\Exceptions\InvalidGradingResponse;
use App\Infrastructure\AI\Exceptions\ProviderFailure;
use App\Infrastructure\AI\OpenRouterGradingProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterGradingProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['formai.ai_provider' => 'openrouter', 'services.openrouter.key' => 'test-key',
            'services.openrouter.model' => 'openai/gpt-4o', 'services.openrouter.response_format' => 'json_schema',
            'services.openrouter.base_url' => 'https://openrouter.test/api/v1']);
        Http::preventStrayRequests();
    }

    private function request(array $destination = []): GradingRequest
    {
        return new GradingRequest('Explique o tema.', '', [], 'Resposta do aluno.', '', 10, 1, 'pt-BR',
            str_repeat('a', 64), str_repeat('b', 64), systemInstruction: 'Instrução congelada.',
            destination: $destination, composedInput: 'Entrada congelada sem identificação.');
    }

    private function payload(): array
    {
        return ['choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode([
            'score' => 8, 'criterion_scores' => [['criterion' => 'Qualidade geral da resposta', 'score' => 8, 'justification' => 'Boa resposta.']],
            'evidence' => ['Resposta do aluno.'], 'feedback' => 'Feedback ao aluno.', 'teacher_feedback' => 'Análise privada.',
            'confidence' => 0.9, 'warnings' => [],
        ])]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 40]];
    }

    public function test_chat_completions_preserves_snapshot_schema_feedback_and_usage(): void
    {
        Http::fake(['openrouter.test/*' => Http::response($this->payload())]);
        $result = app(AiGradingProvider::class)->grade($this->request());
        $this->assertInstanceOf(OpenRouterGradingProvider::class, app(AiGradingProvider::class));
        $this->assertSame(8.0, $result->score);
        $this->assertSame('Feedback ao aluno.', $result->feedback);
        $this->assertSame('Análise privada.', $result->teacherFeedback);
        $this->assertSame(100, $result->inputTokens);
        $this->assertSame(40, $result->outputTokens);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://openrouter.test/api/v1/chat/completions'
            && $r->hasHeader('Authorization', 'Bearer test-key')
            && $r['model'] === 'openai/gpt-4o'
            && $r['messages'] === [['role' => 'system', 'content' => 'Instrução congelada.'],
                ['role' => 'user', 'content' => 'Entrada congelada sem identificação.']]
            && $r['response_format']['type'] === 'json_schema'
            && $r['response_format']['json_schema']['strict'] === true
            && $r['stream'] === false && $r['max_tokens'] === 8192
            && ! isset($r['provider'], $r['reasoning'], $r['input']));
    }

    public function test_destination_overrides_default_model_and_output_budget(): void
    {
        Http::fake(['openrouter.test/*' => Http::response($this->payload())]);
        app(OpenRouterGradingProvider::class)->grade($this->request(['model' => 'openai/gpt-4o-2024-08-06', 'max_output_tokens' => 5000, 'effort' => 'omit']));
        Http::assertSent(fn (Request $r) => $r['model'] === 'openai/gpt-4o-2024-08-06' && $r['max_tokens'] === 5000);
    }

    public function test_space_bunny_enables_reasoning_with_the_supported_boolean_shape(): void
    {
        Http::fake(['openrouter.test/*' => Http::response($this->payload())]);
        app(OpenRouterGradingProvider::class)->grade($this->request([
            'model' => 'stealth/space-bunny-alpha', 'effort' => 'enabled', 'response_format' => 'json_object',
        ]));
        Http::assertSent(fn (Request $r) => $r['model'] === 'stealth/space-bunny-alpha'
            && $r['reasoning'] === ['enabled' => true]
            && $r['response_format'] === ['type' => 'json_object']
            && str_contains($r['messages'][0]['content'], '"teacher_feedback"')
            && ! isset($r['provider']));
    }

    public function test_free_gemma_uses_json_mode_and_schema_in_system_instruction(): void
    {
        Http::fake(['openrouter.test/*' => Http::response($this->payload())]);
        $result = app(OpenRouterGradingProvider::class)->grade($this->request([
            'model' => 'google/gemma-4-31b-it:free', 'response_format' => 'json_object', 'effort' => 'none',
        ]));
        $this->assertSame(8.0, $result->score);
        Http::assertSent(fn (Request $r) => $r['model'] === 'google/gemma-4-31b-it:free'
            && $r['response_format'] === ['type' => 'json_object']
            && str_starts_with($r['messages'][0]['content'], 'Instrução congelada.')
            && str_contains($r['messages'][0]['content'], '"teacher_feedback"')
            && str_contains($r['messages'][0]['content'], '"maximum":10')
            && $r['messages'][1]['content'] === 'Entrada congelada sem identificação.'
            && $r['reasoning'] === ['effort' => 'none']);
    }

    public function test_unknown_response_format_prevents_http_call(): void
    {
        try {
            app(OpenRouterGradingProvider::class)->grade($this->request(['response_format' => 'invalid']));
            $this->fail('Formato desconhecido aceito.');
        } catch (ProviderFailure $exception) {
            $this->assertSame('invalid_request', $exception->kind);
            Http::assertNothingSent();
        }
    }

    public function test_missing_key_prevents_http_call(): void
    {
        config(['services.openrouter.key' => '']);
        try {
            app(OpenRouterGradingProvider::class)->grade($this->request());
            $this->fail('A chave ausente deve bloquear a chamada.');
        } catch (ProviderFailure $exception) {
            $this->assertSame('unavailable', $exception->kind);
            Http::assertNothingSent();
        }
    }

    public function test_refusal_truncation_and_invalid_json_keep_usage_without_a_grade(): void
    {
        config(['services.openrouter.response_format' => 'json_object']);
        $payloads = [];
        foreach (['length', 'content_filter'] as $reason) {
            $payload = $this->payload();
            $payload['choices'][0]['finish_reason'] = $reason;
            $payloads[] = $payload;
        }
        $refusal = $this->payload();
        $refusal['choices'][0]['message']['refusal'] = 'Recusa.';
        $payloads[] = $refusal;
        $invalid = $this->payload();
        $invalid['choices'][0]['message']['content'] = '{"score":99}';
        $payloads[] = $invalid;
        $invalid['choices'][0]['message']['content'] = 'JSON inválido';
        $payloads[] = $invalid;
        $sequence = Http::sequence();
        foreach ($payloads as $payload) {
            $sequence->push($payload);
        }
        Http::fake(['openrouter.test/*' => $sequence]);
        foreach ($payloads as $payload) {
            try {
                app(OpenRouterGradingProvider::class)->grade($this->request());
                $this->fail('Resposta inválida aceita.');
            } catch (InvalidGradingResponse $exception) {
                $this->assertSame(100, $exception->inputTokens);
                $this->assertSame(40, $exception->outputTokens);
            }
        }
    }

    public function test_http_errors_and_errors_inside_http_200_are_classified(): void
    {
        $cases = [[402, 402, 'quota'], [429, 429, 'rate_limit'], [401, 401, 'unavailable'],
            [503, 503, 'technical'], [200, 503, 'technical'], [200, 402, 'quota']];
        $sequence = Http::sequence();
        foreach ($cases as [$status, $code, $kind]) {
            $sequence->push(['error' => ['code' => $code]], $status, ['Retry-After' => '60']);
        }
        Http::fake(['openrouter.test/*' => $sequence]);
        foreach ($cases as [$status, $code, $kind]) {
            try {
                app(OpenRouterGradingProvider::class)->grade($this->request());
                $this->fail('Erro da API aceito.');
            } catch (ProviderFailure $exception) {
                $this->assertSame($kind, $exception->kind);
                $this->assertSame(60, $exception->retryAfter);
            }
        }
    }

    public function test_connection_and_certificate_failures_are_classified(): void
    {
        $messages = ['cURL error 28', 'cURL error 60'];
        Http::fake(function () use (&$messages) {
            throw new ConnectionException(array_shift($messages));
        });
        foreach (['technical', 'certificate'] as $kind) {
            try {
                app(OpenRouterGradingProvider::class)->grade($this->request());
                $this->fail('Falha de conexão aceita.');
            } catch (ProviderFailure $exception) {
                $this->assertSame($kind, $exception->kind);
            }
        }
    }

    public function test_configuration_recognizes_openrouter_when_it_is_the_only_key(): void
    {
        config(['services.gemini.key' => null, 'services.openai.key' => null]);
        $configuration = app(AiProviderConfiguration::class);
        $this->assertTrue($configuration->isConfigured());
        $this->assertSame('openrouter', $configuration->provider());
        $this->assertSame('OPENROUTER_API_KEY', $configuration->keyEnvironmentName());
        $this->assertSame('openai/gpt-4o', $configuration->model());
    }
}
