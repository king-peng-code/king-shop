<?php

namespace App\Domain\Storage\Entities;

final class Upload
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $originalName,
        public readonly string $path,
        public readonly string $disk,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly ?string $md5,
        public readonly ?int $uploadedBy,
    ) {}
}
