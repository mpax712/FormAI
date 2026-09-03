<?php

namespace Tests\Unit;

use App\Application\DTOs\GradingResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GradingResultTest extends TestCase
{
    public function test_confidence_must_be_normalized(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GradingResult(1, [], [], '', 1.1, []);
    }
}
