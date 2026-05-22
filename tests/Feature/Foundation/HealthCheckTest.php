<?php

namespace Tests\Feature\Foundation;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_api_health_check_returns_standard_response(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Service is healthy',
                'data' => [
                    'status' => 'ok',
                    'environment' => 'testing',
                ],
                'meta' => [],
            ]);
    }
}
