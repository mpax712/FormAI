<?php

namespace App\Infrastructure\AI;

use App\Application\DTOs\GradingRequest;
use App\Application\DTOs\GradingResult;
use App\Domain\Grading\Contracts\AiGradingProvider;
use App\Infrastructure\AI\Exceptions\InvalidGradingResponse;
use App\Infrastructure\AI\Exceptions\PermanentAiException;
use App\Infrastructure\AI\Exceptions\ProviderFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiGradingProvider implements AiGradingProvider
{
    public function __construct(
        private readonly PromptComposer $prompts = new PromptComposer,
        private readonly GradingResponseMapper $responses = new GradingResponseMapper,
    ) {}

    /** @throws ConnectionException */
    public function grade(GradingRequest $request): GradingResult
    {
        $key = config('services.openai.key');
        if (! is_string($key) || $key === '') {
            throw new ProviderFailure('unavailable', 'Credencial OpenAI não configurada.');
        }

        try {
            $response = Http::baseUrl(config('services.openai.base_url'))
                ->withToken($key)->acceptJson()->asJson()
                ->connectTimeout(5)->timeout(min(60, config('services.openai.timeout')))
                ->post('/responses', [
                    'model' => $request->destination['model'] ?? config('services.openai.model'),
                    'instructions' => $request->systemInstruction ?? $this->prompts->systemPrompt($request),
                    'input' => $this->prompts->input($request),
                    'store' => false,
                    ...(($request->destination['effort'] ?? '') === 'omit' ? [] : ['reasoning' => ['effort' => $request->destination['effort'] ?? config('services.openai.reasoning_effort')]]),
                    'max_output_tokens' => $request->destination['max_output_tokens'] ?? config('services.openai.max_output_tokens'),
                    'safety_identifier' => $request->safetyIdentifier,
                    'text' => ['format' => [
                        'type' => 'json_schema', 'name' => 'grading_result', 'strict' => true,
                        'schema' => $request->responseSchema ?? $this->responses->schema($request->maximumScore),
                    ]],
                ]);

        } catch (ConnectionException $exception) {
            throw new ProviderFailure(
                str_contains($exception->getMessage(), 'cURL error 60') ? 'certificate' : 'technical',
                'Não foi possível estabelecer uma conexão segura com o provedor OpenAI.');
        }
        ProviderErrors::check($response);

        $payload = $response->json();
        try {
            if (($payload['status'] ?? null) === 'incomplete') {
                throw new PermanentAiException('A saída da IA foi interrompida; revise o orçamento de saída do perfil.');
            }
            $text = $payload['output_text'] ?? $this->extractOutputText($payload['output'] ?? []);
            $data = json_decode((string) $text, true, flags: JSON_THROW_ON_ERROR);

            return $this->responses->map(
                $data,
                $request,
                $payload['usage']['input_tokens'] ?? null,
                $payload['usage']['output_tokens'] ?? null,
            );
        } catch (\Throwable $exception) {
            throw new InvalidGradingResponse(
                isset($payload['usage']['input_tokens']) ? (int) $payload['usage']['input_tokens'] : null,
                isset($payload['usage']['output_tokens']) ? (int) $payload['usage']['output_tokens'] : null,
                $exception,
            );
        }
    }

    private function extractOutputText(array $output): string
    {
        foreach ($output as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    return $content['text'];
                }
            }
        }
        throw new RuntimeException('Resposta da IA nao continha texto estruturado.');
    }
}
