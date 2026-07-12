<?php

namespace App\Http\Controllers;

use App\Application\Identity\ChangePassword\ChangePasswordHandler;
use App\Application\Identity\GetCurrentUser\GetCurrentUserHandler;
use App\Application\Identity\Login\LoginHandler;
use App\Application\Identity\Logout\LogoutHandler;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthUserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginHandler $handler): JsonResponse
    {
        $result = $handler->handle(
            $request->validated('phone'),
            $request->validated('password'),
        );

        return ApiResponse::success([
            'token' => $result->token,
            'user' => new AuthUserResource($result->user),
            'must_change_password' => $result->mustChangePassword,
        ]);
    }

    public function logout(Request $request, LogoutHandler $handler): JsonResponse
    {
        $handler->handle($request->user(), $request->user()->currentAccessToken());

        return ApiResponse::success();
    }

    public function me(Request $request, GetCurrentUserHandler $handler): JsonResponse
    {
        $user = $handler->handle($request->user()->id);

        return ApiResponse::success(new AuthUserResource($user));
    }

    public function changePassword(
        ChangePasswordRequest $request,
        ChangePasswordHandler $handler,
    ): JsonResponse {
        $handler->handle(
            $request->user()->id,
            $request->validated('current_password'),
            $request->validated('new_password'),
        );

        return ApiResponse::success();
    }
}
