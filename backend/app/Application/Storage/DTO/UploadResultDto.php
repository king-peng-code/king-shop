<?php

namespace App\Application\Storage\DTO;

readonly class UploadResultDto
{
    public function __construct(
        public int $id,
        public string $url,
        public string $path,
        public string $filename,
        public int $size,
    ) {}
}
