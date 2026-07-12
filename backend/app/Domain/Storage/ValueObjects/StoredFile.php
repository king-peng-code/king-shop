<?php

namespace App\Domain\Storage\ValueObjects;

readonly class StoredFile
{
    public function __construct(
        public string $path,
        public string $disk,
    ) {}

    public function filename(): string
    {
        return basename($this->path);
    }
}
