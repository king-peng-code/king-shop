<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['admin', 'super_admin'], true)) {
            return ApiResponse::error(403, '无权访问', null, 403);
        }

        if ($user->status !== 'active') {
            return ApiResponse::error(403, '账号已禁用', null, 403);
        }

        return $next($request);
    }
}
