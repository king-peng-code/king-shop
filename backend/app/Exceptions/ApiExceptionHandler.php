<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ApiExceptionHandler
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof BusinessException) {
                return ApiResponse::error(
                    $e->businessCode,
                    $e->getMessage(),
                    null,
                    $e->httpStatus,
                );
            }

            if ($e instanceof ValidationException) {
                return ApiResponse::validationError($e->errors());
            }

            if ($e instanceof HttpExceptionInterface) {
                return ApiResponse::error(
                    $e->getStatusCode(),
                    $e->getMessage() ?: 'Request failed',
                    null,
                    $e->getStatusCode(),
                );
            }

            if (config('app.debug')) {
                return null;
            }

            return ApiResponse::error(500, 'Internal server error', null, 500);
        });
    }
}
