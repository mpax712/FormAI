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

class OpenRouterGradingProvider implements AiGradingProvider
{
    public function __construct(
        private readonly PromptComposer $prompts = new PromptComposer,
        private readonly GradingResponseMapper $responses = new GradingResponseMapper,
    ) {}

    public function grade(GradingRequest $request): GradingResult
    {
        $key = config('services.openrouter.key');
        if (! is_string($key) || $key === '') {
            throw new ProviderFailure('unavailable', 'Credencial OpenRouter não configurada.');
        }

        $format = $request->destination['response_format'] ?? config('services.openrouter.response_format');
        if (! in_array($format, ['json_object', 'json_schema'], true)) {
            throw new ProviderFailure('invalid_request', 'Formato de resposta OpenRouter não suportado.');
        }
        $schema = $request->responseSchema ?? $this->responses->schema($request->maximumScore);
        $system = $request->systemInstruction ?? $this->prompts->systemPrompt($request);
        if ($format === 'json_object') {
            $system .= "\n\nRetorne somente um objeto JSON válido, sem Markdown nem texto adicional. "
                .'Inclua todos os campos obrigatórios e respeite os critérios e os limites de pontuação. '
                .'O JSON deve seguir este schema: '.json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        $effort = $request->destination['effort'] ?? 'omit';
        $reasoning = match ($effort) {
            'omit' => [],
            'enabled' => ['reasoning' => ['enabled' => true]],
            'disabled' => ['reasoning' => ['enabled' => false]],
            default => ['reasoning' => ['effort' => $effort]],
        };

        try {
            $response = Http::baseUrl(config('services.openrouter.base_url'))
                ->withToken($key)->acceptJson()->asJson()
                ->connectTimeout(config('services.openrouter.connect_timeout'))
                ->timeout(min(60, config('services.openrouter.timeout')))
                ->post('/chat/completions', [
                    'model' => $request->destination['model'] ?? config('services.openrouter.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $this->prompts->input($request)],
                    ],
                    'stream' => false,
                    'max_tokens' => $request->destination['max_output_tokens'] ?? config('services.openrouter.max_output_tokens'),
                    'temperature' => config('services.openrouter.temperature'),
                    ...$reasoning,
                    'response_format' => [
                        'type' => $format,
                        ...($format === 'json_schema' ? ['json_schema' => [
                            'name' => 'grading_result', 'strict' => true,
                            'schema' => $schema,
                        ]] : []),
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new ProviderFailure(
                str_contains($exception->getMessage(), 'cURL error 60') ? 'certificate' : 'technical',
                'Não foi possível estabelecer uma conexão segura com o provedor OpenRouter.',
            );
        }
        ProviderErrors::check($response);

        $payload = $response->json();
        $inputTokens = isset($payload['usage']['prompt_tokens']) ? (int) $payload['usage']['prompt_tokens'] : null;
        $outputTokens = isset($payload['usage']['completion_tokens']) ? (int) $payload['usage']['completion_tokens'] : null;
        try {
            $choice = $payload['choices'][0] ?? [];
            if (($choice['finish_reason'] ?? null) !== 'stop' || ! empty($choice['message']['refusal'])) {
                throw new PermanentAiException('A IA recusou o pedido ou a saída foi interrompida.');
            }
            $data = json_decode($choice['message']['content'] ?? '', true, flags: JSON_THROW_ON_ERROR);

            return $this->responses->map($data, $request, $inputTokens, $outputTokens);
        } catch (\Throwable $exception) {
            throw new InvalidGradingResponse($inputTokens, $outputTokens, $exception);
        }
    }
}
