<?php

namespace App\Infrastructure\Storage;

use App\Domain\Storage\Services\PublicUrlGeneratorInterface;

class ConfigPublicUrlGenerator implements PublicUrlGeneratorInterface
{
    public function generate(string $path, string $disk): string
    {
        $path = ltrim($path, '/');

        return match ($disk) {
            'oss' => rtrim(config('storage.oss_public_base_url'), '/').'/'.$path,
            default => rtrim(config('storage.public_base_url'), '/').'/storage/'.$path,
        };
    }
}
