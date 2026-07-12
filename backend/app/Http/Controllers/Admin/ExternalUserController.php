<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\ExternalUser\DTO\UpdateExternalUserCommand;
use App\Application\ExternalUser\ListExternalUsers\ListExternalUsersHandler;
use App\Application\ExternalUser\UpdateExternalUser\UpdateExternalUserHandler;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ExternalUserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalUserController extends Controller
{
    public function index(Request $request, ListExternalUsersHandler $handler): JsonResponse
    {
        $result = $handler->handle(
            keyword: (string) $request->query('keyword', ''),
            page: max(1, (int) $request->query('page', 1)),
            perPage: max(1, min(100, (int) $request->query('per_page', 20))),
        );

        return ApiResponse::success([
            'items' => ExternalUserResource::collection($result['items']),
            'meta' => $result['meta'],
        ]);
    }

    public function update(Request $request, int $externalUser, UpdateExternalUserHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:11',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        $handler->handle(
            new UpdateExternalUserCommand(
                id: $externalUser,
                name: $validated['name'] ?? null,
                phone: $validated['phone'] ?? null,
                tags: $validated['tags'] ?? [],
            ),
        );

        return ApiResponse::success();
    }
}
