<?php

namespace App\Domain\Storage\Services;

interface PublicUrlGeneratorInterface
{
    public function generate(string $path, string $disk): string;
}
