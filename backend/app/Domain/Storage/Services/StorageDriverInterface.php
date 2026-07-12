<?php

namespace App\Domain\Storage\Services;

use App\Domain\Storage\ValueObjects\StoredFile;

interface StorageDriverInterface
{
    public function store(string $contents, string $extension, string $mimeType): StoredFile;
}
