<?php

namespace App\Infrastructure\AI;

use App\Application\DTOs\GradingRequest;
use App\Application\DTOs\GradingResult;
use App\Domain\Grading\Contracts\AiGradingProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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
            throw new RuntimeException('GEMINI_API_KEY não configurada. A correção manual continua disponível.');
        }

        $response = Http::baseUrl(config('services.gemini.base_url'))
            ->withHeaders(['x-goog-api-key' => $key])
            ->acceptJson()->asJson()
            ->timeout(config('services.gemini.timeout'))->retry(2, 500, throw: false)
            ->post('/interactions', [
                'model' => config('services.gemini.model'),
                'system_instruction' => $this->prompts->systemPrompt(),
                'input' => $this->prompts->input($request),
                'generation_config' => [
                    'max_output_tokens' => config('services.gemini.max_output_tokens'),
                    'temperature' => config('services.gemini.temperature'),
                ],
                'response_format' => [
                    'type' => 'text',
                    'mime_type' => 'application/json',
                    'schema' => $this->responses->schema($request->maximumScore),
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Falha do provedor Gemini: HTTP '.$response->status());
        }

        $payload = $response->json();
        if (($payload['status'] ?? 'completed') !== 'completed') {
            throw new RuntimeException('O Gemini não concluiu a correção. Estado: '.($payload['status'] ?? 'desconhecido').'.');
        }

        $data = json_decode($this->extractOutputText($payload), true, flags: JSON_THROW_ON_ERROR);

        return $this->responses->map(
            $data,
            $request,
            isset($payload['usage']['total_input_tokens']) ? (int) $payload['usage']['total_input_tokens'] : null,
            isset($payload['usage']['total_output_tokens']) ? (int) $payload['usage']['total_output_tokens'] : null,
        );
    }

    private function extractOutputText(array $payload): string
    {
        if (is_string($payload['output_text'] ?? null)) {
            return $payload['output_text'];
        }

        foreach (array_reverse($payload['steps'] ?? []) as $step) {
            if (($step['type'] ?? null) !== 'model_output') continue;
            foreach ($step['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('A resposta do Gemini não continha o resultado estruturado.');
    }
}
