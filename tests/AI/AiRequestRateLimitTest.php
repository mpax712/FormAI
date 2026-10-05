<?php

namespace Tests\AI;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AiRequestRateLimitTest extends TestCase
{
    public function test_teacher_request_limits_use_distinct_minute_and_hour_counters(): void
    {
        $request = Request::create('/teacher/ai', 'POST');
        $user = new User;
        $user->id = 42;
        $request->setUserResolver(fn () => $user);

        $limits = (RateLimiter::limiter('ai'))($request);

        $this->assertCount(2, $limits);
        $this->assertSame(3, $limits[0]->maxAttempts);
        $this->assertSame(60, $limits[0]->decaySeconds);
        $this->assertSame(20, $limits[1]->maxAttempts);
        $this->assertSame(3600, $limits[1]->decaySeconds);
        $this->assertNotSame($limits[0]->key, $limits[1]->key);
    }
}
