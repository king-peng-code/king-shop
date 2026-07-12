<?php

namespace Tests\Unit;

use App\Http\Responses\ApiResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    #[Test]
    public function success_response_has_standard_shape(): void
    {
        $response = ApiResponse::success(['status' => 'healthy']);

        $payload = $response->getData(true);

        $this->assertSame(0, $payload['code']);
        $this->assertSame('ok', $payload['message']);
        $this->assertSame(['status' => 'healthy'], $payload['data']);
        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function error_response_has_standard_shape(): void
    {
        $response = ApiResponse::error(1001, 'Resource not found', null, 404);

        $payload = $response->getData(true);

        $this->assertSame(1001, $payload['code']);
        $this->assertSame('Resource not found', $payload['message']);
        $this->assertNull($payload['data']);
        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function validation_error_response_has_standard_shape(): void
    {
        $errors = ['email' => ['The email field is required.']];
        $response = ApiResponse::validationError($errors);

        $payload = $response->getData(true);

        $this->assertSame(422, $payload['code']);
        $this->assertSame('Validation failed', $payload['message']);
        $this->assertSame(['errors' => $errors], $payload['data']);
        $this->assertSame(422, $response->getStatusCode());
    }
}
