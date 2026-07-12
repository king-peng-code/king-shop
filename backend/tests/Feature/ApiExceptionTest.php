<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiExceptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/v1/test/business-exception', function () {
            throw new BusinessException(1001, 'Resource not found', 404);
        });

        Route::post('/api/v1/test/validation-error', function (\Illuminate\Http\Request $request) {
            $request->validate([
                'email' => ['required', 'email'],
            ]);
        });
    }

    #[Test]
    public function business_exception_returns_standard_api_format(): void
    {
        $response = $this->getJson('/api/v1/test/business-exception');

        $response
            ->assertStatus(404)
            ->assertJson([
                'code' => 1001,
                'message' => 'Resource not found',
                'data' => null,
            ]);
    }

    #[Test]
    public function validation_exception_returns_unified_422_format(): void
    {
        $response = $this->postJson('/api/v1/test/validation-error', []);

        $response
            ->assertStatus(422)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => ['errors' => ['email']],
            ])
            ->assertJson([
                'code' => 422,
                'message' => 'Validation failed',
            ]);
    }
}
