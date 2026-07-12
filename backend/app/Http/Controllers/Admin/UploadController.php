<?php

namespace App\Http\Controllers\Admin;

use App\Application\Storage\DTO\UploadImageCommand;
use App\Application\Storage\UploadImage\UploadImageHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadImageRequest;
use App\Http\Resources\Admin\UploadResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class UploadController extends Controller
{
    public function store(
        UploadImageRequest $request,
        UploadImageHandler $handler,
    ): JsonResponse {
        $file = $request->file('file');

        $result = $handler->handle(new UploadImageCommand(
            originalName: $file->getClientOriginalName(),
            contents: $file->get(),
            extension: $file->getClientOriginalExtension() ?: $file->extension(),
            mimeType: $file->getMimeType() ?? 'application/octet-stream',
            size: $file->getSize(),
            uploadedBy: $request->user()?->id,
        ));

        return ApiResponse::success(new UploadResource($result));
    }
}
