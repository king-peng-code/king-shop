<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'ok', int $httpStatus = 200): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'message' => $message,
            'data' => $data ?? new \stdClass(),
        ], $httpStatus);
    }

    public static function error(int $code, string $message, mixed $data = null, int $httpStatus = 400): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $httpStatus);
    }

    public static function validationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return response()->json([
            'code' => 422,
            'message' => $message,
            'data' => ['errors' => $errors],
        ], 422);
    }
}
