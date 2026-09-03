<?php

namespace App\Infrastructure\AI;

use App\Application\DTOs\GradingRequest;
use App\Application\DTOs\GradingResult;
use App\Domain\Grading\Contracts\AiGradingProvider;
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
            throw new RuntimeException('OPENAI_API_KEY nao configurada. A correcao manual continua disponivel.');
        }

        $response = Http::baseUrl(config('services.openai.base_url'))
            ->withToken($key)->acceptJson()->asJson()
            ->timeout(config('services.openai.timeout'))->retry(2, 500, throw: false)
            ->post('/responses', [
                'model' => config('services.openai.model'),
                'instructions' => $this->prompts->systemPrompt(),
                'input' => $this->prompts->input($request),
                'store' => false,
                'reasoning' => ['effort' => config('services.openai.reasoning_effort')],
                'max_output_tokens' => config('services.openai.max_output_tokens'),
                'safety_identifier' => $request->safetyIdentifier,
                'text' => ['verbosity' => 'low', 'format' => [
                    'type' => 'json_schema', 'name' => 'grading_result', 'strict' => true,
                    'schema' => $this->responses->schema($request->maximumScore),
                ]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Falha do provedor de IA: HTTP '.$response->status());
        }

        $payload = $response->json();
        $text = $payload['output_text'] ?? $this->extractOutputText($payload['output'] ?? []);
        $data = json_decode((string) $text, true, flags: JSON_THROW_ON_ERROR);
        return $this->responses->map(
            $data,
            $request,
            $payload['usage']['input_tokens'] ?? null,
            $payload['usage']['output_tokens'] ?? null,
        );
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
