<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointsTest extends TestCase
{
    public function test_liveness_endpoint_does_not_depend_on_the_database(): void
    {
        $this->getJson('/up')
            ->assertOk();
    }

    public function test_readiness_returns_controlled_json_when_database_is_unavailable(): void
    {
        config([
            'database.default' => 'unavailable',
            'database.connections.unavailable' => [
                'driver' => 'sqlite',
                'database' => '/path/that/does/not/exist/formai.sqlite',
            ],
        ]);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'down', 'database' => 'error']);
    }
}
