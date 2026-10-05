<?php

namespace App\Infrastructure\AI;

use App\Domain\Activities\Models\Activity;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GradingProfiles
{
    public const DETAILS = ['short' => 'Curto', 'medium' => 'Médio', 'detailed' => 'Detalhado'];

    public const PROFILES = ['economy' => 'Econômico', 'balanced' => 'Equilibrado', 'advanced' => 'Avançado'];

    public const STRICTNESS = ['flexible' => 'Flexível', 'balanced' => 'Equilibrada', 'strict' => 'Rigorosa'];

    public function options(array $input, ?Activity $activity = null): array
    {
        return Validator::make([
            'feedback_detail' => $input['feedback_detail'] ?? $activity?->feedback_detail ?? 'medium',
            'intelligence_profile' => $input['intelligence_profile'] ?? $activity?->intelligence_profile ?? 'balanced',
            'grading_strictness' => $input['grading_strictness'] ?? $activity?->grading_strictness ?? 'balanced',
        ], [
            'feedback_detail' => ['required', Rule::in(array_keys(self::DETAILS))],
            'intelligence_profile' => ['required', Rule::in(array_keys(self::PROFILES))],
            'grading_strictness' => ['required', Rule::in(array_keys(self::STRICTNESS))],
        ])->validate();
    }

    public function destinations(string $profile, string $detail, int $inputBytes, int $criteria): array
    {
        $config = config("ai_grading.profiles.$profile", []);
        $ceiling = $config['cost_ceiling_usd'] ?? null;
        $requiredOutput = ['short' => 1200, 'medium' => 2200, 'detailed' => 3800][$detail] + 180 * $criteria;
        $destinations = [];
        foreach ($config['destinations'] ?? [] as $destination) {
            $provider = $destination['provider'] ?? '';
            $quota = config('ai_grading.quotas.'.($destination['quota_group'] ?? ''), []);
            if (! ($destination['enabled'] ?? false) || ! in_array($provider, ['gemini', 'openai', 'openrouter'], true)
                || ! filled(config("services.$provider.key")) || ! filled($destination['model'] ?? null)
                || ! is_string($destination['model']) || strlen($destination['model']) > 100
                || ! ($destination['structured_output'] ?? false)
                || ($provider === 'openrouter' && ! in_array($destination['response_format'] ?? config('services.openrouter.response_format'), ['json_object', 'json_schema'], true))
                || ! is_numeric($ceiling) || $ceiling <= 0
                || ! is_numeric($destination['input_price'] ?? null) || $destination['input_price'] < 0
                || ! is_numeric($destination['output_price'] ?? null) || $destination['output_price'] < 0
                || ($destination['max_output_tokens'] ?? 0) < $requiredOutput
                || ! in_array($destination['effort'] ?? null, $destination['supported_efforts'] ?? [], true)
                || count(array_filter(Arr::only($quota, ['rpm', 'rph', 'rpd', 'tpm', 'concurrency']), fn ($n) => is_numeric($n) && $n > 0)) !== 5) {
                continue;
            }
            // Reserve the full configured output, including reasoning tokens where applicable.
            $worstCost = ($inputBytes * $destination['input_price'] + $destination['max_output_tokens'] * $destination['output_price']) / 1_000_000;
            if (min($quota['rpm'], $quota['rph'], $quota['rpd']) * config('ai_grading.margin') < 1) {
                continue;
            }
            if ($worstCost * config('ai_grading.max_calls') > $ceiling) {
                continue;
            }
            if ($inputBytes + $destination['max_output_tokens'] > floor($quota['tpm'] * config('ai_grading.margin'))) {
                continue;
            }
            $destination['reserved_tokens'] = $inputBytes + $destination['max_output_tokens'];
            $destination['quota'] = $quota;
            $destinations[] = $destination;
        }
        if ($destinations === []) {
            throw new DomainException('Este perfil não está disponível para o tamanho desta correção. Configure modelos, capacidades, preços, teto de custo e quotas de IA, ou escolha outro perfil.');
        }

        return $destinations;
    }
}
