<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    #[Test]
    public function health_endpoint_returns_standard_api_format(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'code',
                'message',
                'data' => ['status'],
            ])
            ->assertJson([
                'code' => 0,
                'message' => 'ok',
                'data' => ['status' => 'healthy'],
            ]);
    }
}
