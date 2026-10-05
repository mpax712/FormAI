<?php

namespace App\Infrastructure\AI\Exceptions;

class InvalidGradingResponse extends PermanentAiException
{
    public function __construct(public readonly ?int $inputTokens, public readonly ?int $outputTokens, \Throwable $previous)
    {
        parent::__construct('O provedor retornou um resultado inválido ou recusou a análise.', previous: $previous);
    }
}
