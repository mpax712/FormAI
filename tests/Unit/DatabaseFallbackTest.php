<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DatabaseFallbackTest extends TestCase
{
    public function test_it_keeps_the_primary_selected_when_both_connections_fail(): void
    {
        $this->configureConnections();

        DB::shouldReceive('connection')->with('primary')->once()->andThrow(new RuntimeException('primary unavailable'));
        DB::shouldReceive('connection')->with('fallback')->once()->andThrow(new RuntimeException('fallback unavailable'));
        DB::shouldReceive('purge')->with('primary')->once();
        DB::shouldReceive('purge')->with('fallback')->once();
        DB::shouldNotReceive('setDefaultConnection');
        Log::shouldReceive('error')->once();

        $this->selectFallback();

        $this->assertSame('primary', config('database.default'));
    }

    public function test_it_selects_a_connected_fallback(): void
    {
        $this->configureConnections();

        $fallback = Mockery::mock();
        $fallback->shouldReceive('getPdo')->once()->andReturn(new \stdClass);
        DB::shouldReceive('connection')->with('primary')->once()->andThrow(new RuntimeException('primary unavailable'));
        DB::shouldReceive('connection')->with('fallback')->once()->andReturn($fallback);
        DB::shouldReceive('purge')->with('primary')->once();
        DB::shouldReceive('setDefaultConnection')->with('fallback')->once();
        Log::shouldReceive('warning')->once();

        $this->selectFallback();

        $this->assertSame('fallback', config('database.default'));
    }

    private function configureConnections(): void
    {
        config([
            'database.default' => 'primary',
            'database.fallback.enabled' => true,
            'database.fallback.connection' => 'fallback',
            'database.connections.primary' => ['driver' => 'sqlite'],
            'database.connections.fallback' => ['driver' => 'sqlite'],
        ]);
    }

    private function selectFallback(): void
    {
        $this->app->instance('env', 'local');

        try {
            (new \ReflectionMethod(AppServiceProvider::class, 'useFallbackDatabaseWhenNeeded'))
                ->invoke(new AppServiceProvider($this->app));
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
