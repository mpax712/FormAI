<?php

namespace App\Infrastructure\AI;

use App\Infrastructure\AI\Exceptions\ProviderFailure;
use Illuminate\Http\Client\Response;

class ProviderErrors
{
    public static function check(Response $response): void
    {
        if ($response->successful() && ! $response->json('error')) {
            return;
        }
        $status = $response->status();
        if ($response->successful() && is_numeric($response->json('error.code'))) {
            $status = (int) $response->json('error.code');
        }
        $code = strtolower((string) $response->json('error.code', '').' '.(string) $response->json('error.status', '').' '.(string) $response->json('error.type', ''));
        $quotaDetails = strtolower(json_encode($response->json('error.details', [])));
        $kind = match (true) {
            $status === 402 => 'quota',
            str_contains($code, 'insufficient_quota'), str_contains($code, 'billing'), str_contains($code, 'payment') => 'quota',
            $status === 429 && (str_contains($quotaDetails, 'perday') || str_contains($quotaDetails, 'per_day')) => 'quota',
            str_contains($code, 'content_policy'), str_contains($code, 'safety'), str_contains($code, 'refusal') => 'invalid_request',
            $status === 429 => 'rate_limit',
            in_array($status, [401, 403, 404], true) => 'unavailable',
            $status >= 500 || $status === 408 => 'technical',
            default => 'invalid_request',
        };
        $header = $response->header('Retry-After');
        $delay = is_numeric($header) ? (int) $header : max(0, (strtotime($header ?: '') ?: time()) - time());
        $retryInfo = collect($response->json('error.details', []))->first(fn ($item) => str_ends_with($item['@type'] ?? '', 'RetryInfo'));
        if (isset($retryInfo['retryDelay'])) {
            $delay = max($delay, (int) ceil((float) $retryInfo['retryDelay']));
        }
        $message = match ($kind) {
            'quota' => 'Quota ou saldo da API esgotado. Verifique a conta do provedor.',
            'rate_limit' => 'Aguardando liberação do limite da API.',
            'unavailable' => 'Credencial ou modelo indisponível no provedor.',
            'technical' => 'Falha técnica do provedor.',
            default => 'O provedor rejeitou o pedido de correção.',
        };
        throw new ProviderFailure($kind, $message.' HTTP '.$status, $delay);
    }
}
