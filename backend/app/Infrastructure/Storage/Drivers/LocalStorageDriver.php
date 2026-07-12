<?php

namespace App\Infrastructure\Storage\Drivers;

use App\Domain\Storage\Exceptions\StorageException;
use App\Domain\Storage\Services\StorageDriverInterface;
use App\Domain\Storage\ValueObjects\StoredFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LocalStorageDriver implements StorageDriverInterface
{
    public function store(string $contents, string $extension, string $mimeType): StoredFile
    {
        $path = sprintf(
            'uploads/%s/%s.%s',
            now()->format('Y/m'),
            Str::uuid(),
            ltrim($extension, '.'),
        );

        $saved = Storage::disk('public')->put($path, $contents);

        if (! $saved) {
            throw new StorageException('Failed to store file on local disk.');
        }

        return new StoredFile(
            path: $path,
            disk: 'local',
        );
    }
}
