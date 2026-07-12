<?php

namespace App\Http\Resources\Admin;

use App\Application\Storage\DTO\UploadResultDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UploadResultDto */
class UploadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'path' => $this->path,
            'filename' => $this->filename,
            'size' => $this->size,
        ];
    }
}
