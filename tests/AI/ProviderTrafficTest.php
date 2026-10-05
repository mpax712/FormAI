<?php

namespace Tests\AI;

use App\Infrastructure\AI\GradingProfiles;
use App\Infrastructure\AI\ProviderTraffic;
use Tests\Concerns\ConfiguresGrading;
use Tests\TestCase;

class ProviderTrafficTest extends TestCase
{
    use ConfiguresGrading;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureGrading();
    }

    private function destination(): array
    {
        return app(GradingProfiles::class)->destinations('balanced', 'medium', 1000, 1)[0];
    }

    public function test_hour_and_day_limits_apply_with_margin(): void
    {
        foreach (['rph' => 3600, 'rpd' => 86400] as $limit => $seconds) {
            app(ProviderTraffic::class)->store()->flush();
            config(["ai_grading.quotas.gemini.$limit" => 2]);
            $d = $this->destination();
            $traffic = app(ProviderTraffic::class);
            $first = $traffic->acquire($d);
            $traffic->release($d, $first['token']);
            $this->travel(61)->seconds();
            $second = $traffic->acquire($d);
            $this->assertArrayNotHasKey('token', $second);
            $this->assertGreaterThan($seconds - 62, $second['delay']);
            config(["ai_grading.quotas.gemini.$limit" => 10000]);
        }
    }

    public function test_token_reservation_and_three_failure_circuit(): void
    {
        config(['ai_grading.quotas.gemini.tpm' => 8000]);
        $d = $this->destination();
        $traffic = app(ProviderTraffic::class);
        $first = $traffic->acquire($d);
        $traffic->release($d, $first['token']);
        $this->travel(2)->seconds();
        $this->assertGreaterThan(50, $traffic->acquire($d)['delay']);
        for ($i = 0; $i < 3; $i++) {
            $traffic->failure($d, 'technical', 20);
        }
        $this->assertSame('provider_paused', $traffic->acquire($d)['reason']);
        $this->travel(301)->seconds();
        $this->assertArrayHasKey('token', $traffic->acquire($d));
    }

    public function test_file_cache_is_atomic_across_processes(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires pcntl.');
        }
        config(['ai_grading.cache_store' => 'file']);
        $d = $this->destination();
        $d['quota_group'] = 'test-'.bin2hex(random_bytes(12));
        config(['ai_grading.quotas.'.$d['quota_group'] => $d['quota']]);
        $traffic = app(ProviderTraffic::class);
        $children = [];
        for ($i = 0; $i < 3; $i++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                $permit = $traffic->acquire($d);
                exit(isset($permit['token']) ? 10 : 11);
            }
            $this->assertGreaterThan(0, $pid);
            $children[] = $pid;
        }
        $admitted = 0;
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $code = pcntl_wexitstatus($status);
            $this->assertContains($code, [10, 11]);
            if ($code === 10) {
                $admitted++;
            }
        }
        $this->assertSame(1, $admitted);
        // Delete only the test's own isolated quota entry, never the application's cache.
        $traffic->store()->forget('ai:quota:'.hash('sha256', $d['provider'].':'.$d['quota_group']));
    }
}
