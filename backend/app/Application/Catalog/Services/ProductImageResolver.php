<?php

namespace App\Application\Catalog\Services;

use App\Domain\Storage\Repositories\UploadRepositoryInterface;
use App\Domain\Storage\Services\PublicUrlGeneratorInterface;

class ProductImageResolver
{
    public function __construct(
        private readonly UploadRepositoryInterface $uploadRepository,
        private readonly PublicUrlGeneratorInterface $urlGenerator,
    ) {}

    public function resolveUrl(?string $imagePath, ?int $uploadId): ?string
    {
        if ($imagePath === null || $imagePath === '') {
            return null;
        }

        $disk = 'local';
        if ($uploadId !== null) {
            $upload = $this->uploadRepository->findById($uploadId);
            if ($upload !== null) {
                $disk = $upload->disk;
            }
        }

        return $this->urlGenerator->generate($imagePath, $disk);
    }
}
