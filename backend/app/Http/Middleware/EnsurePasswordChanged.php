<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    private const ALLOWED_PATHS = [
        'api/v1/auth/me',
        'api/v1/auth/password',
        'api/v1/auth/logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            if (! in_array($request->path(), self::ALLOWED_PATHS, true)) {
                return ApiResponse::error(40301, '请先修改密码', null, 403);
            }
        }

        return $next($request);
    }
}
