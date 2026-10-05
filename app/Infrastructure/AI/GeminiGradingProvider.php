<?php

namespace App\Infrastructure\AI;

use App\Application\DTOs\GradingRequest;
use App\Application\DTOs\GradingResult;
use App\Domain\Grading\Contracts\AiGradingProvider;
use App\Infrastructure\AI\Exceptions\InvalidGradingResponse;
use App\Infrastructure\AI\Exceptions\PermanentAiException;
use App\Infrastructure\AI\Exceptions\ProviderFailure;
use App\Infrastructure\AI\Exceptions\RetryableAiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GeminiGradingProvider implements AiGradingProvider
{
    public function __construct(
        private readonly PromptComposer $prompts = new PromptComposer,
        private readonly GradingResponseMapper $responses = new GradingResponseMapper,
    ) {}

    /** @throws ConnectionException */
    public function grade(GradingRequest $request): GradingResult
    {
        $key = config('services.gemini.key');
        if (! is_string($key) || $key === '') {
            throw new ProviderFailure('unavailable', 'Credencial Gemini não configurada.');
        }

        try {
            $response = Http::baseUrl(config('services.gemini.base_url'))
                ->withHeaders(['x-goog-api-key' => $key])
                ->acceptJson()->asJson()
                ->connectTimeout(config('services.gemini.connect_timeout'))
                ->timeout(min(60, config('services.gemini.timeout')))
                ->post('/models/'.rawurlencode($request->destination['model'] ?? config('services.gemini.model')).':generateContent', [
                    'system_instruction' => [
                        'parts' => [['text' => $request->systemInstruction ?? $this->prompts->systemPrompt($request)]],
                    ],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $this->prompts->input($request)]],
                    ]],
                    'generationConfig' => [
                        'maxOutputTokens' => $request->destination['max_output_tokens'] ?? config('services.gemini.max_output_tokens'),
                        ...(($request->destination['effort'] ?? '') === 'omit' ? [] : ['thinkingConfig' => [
                            'thinkingLevel' => $request->destination['effort'] ?? config('services.gemini.thinking_level'),
                        ]]),
                        'responseMimeType' => 'application/json',
                        'responseJsonSchema' => $request->responseSchema ?? $this->responses->schema($request->maximumScore),
                    ],
                ]);
        } catch (ConnectionException $exception) {
            if (str_contains($exception->getMessage(), 'cURL error 60')) {
                throw new ProviderFailure('certificate', 'Falha ao validar o certificado SSL do Gemini.');
            }

            throw new RetryableAiException('Falha de conexão ou timeout no Gemini.', previous: $exception);
        }

        ProviderErrors::check($response);

        $payload = $response->json();
        try {
            if (isset($payload['promptFeedback']['blockReason']) || (isset($payload['candidates'][0]['finishReason']) && $payload['candidates'][0]['finishReason'] !== 'STOP')) {
                throw new PermanentAiException('O provedor interrompeu ou recusou a análise. A correção manual continua disponível.');
            }
            $data = json_decode($this->extractOutputText($payload), true, flags: JSON_THROW_ON_ERROR);

            return $this->responses->map(
                $data,
                $request,
                isset($payload['usageMetadata']['promptTokenCount']) ? (int) $payload['usageMetadata']['promptTokenCount'] : null,
                isset($payload['usageMetadata']['candidatesTokenCount']) ? (int) $payload['usageMetadata']['candidatesTokenCount'] + (int) ($payload['usageMetadata']['thoughtsTokenCount'] ?? 0) : null,
            );
        } catch (\Throwable $exception) {
            throw new InvalidGradingResponse(
                isset($payload['usageMetadata']['promptTokenCount']) ? (int) $payload['usageMetadata']['promptTokenCount'] : null,
                isset($payload['usageMetadata']['candidatesTokenCount']) ? (int) $payload['usageMetadata']['candidatesTokenCount'] + (int) ($payload['usageMetadata']['thoughtsTokenCount'] ?? 0) : null,
                $exception,
            );
        }
    }

    private function extractOutputText(array $payload): string
    {
        $text = $payload['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (is_string($text) && $text !== '') {
            return $text;
        }

        throw new PermanentAiException('A resposta do Gemini não continha o resultado estruturado.');
    }
}
