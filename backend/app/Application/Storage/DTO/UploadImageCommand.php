<?php

namespace App\Application\Storage\DTO;

readonly class UploadImageCommand
{
    public function __construct(
        public string $originalName,
        public string $contents,
        public string $extension,
        public string $mimeType,
        public int $size,
        public ?int $uploadedBy,
    ) {}
}
