<?php

namespace App\Application\Storage\UploadImage;

use App\Application\Storage\DTO\UploadImageCommand;
use App\Application\Storage\DTO\UploadResultDto;
use App\Domain\Storage\Entities\Upload;
use App\Domain\Storage\Repositories\UploadRepositoryInterface;
use App\Domain\Storage\Services\PublicUrlGeneratorInterface;
use App\Domain\Storage\Services\StorageDriverResolverInterface;

class UploadImageHandler
{
    public function __construct(
        private readonly StorageDriverResolverInterface $driverResolver,
        private readonly UploadRepositoryInterface $uploadRepository,
        private readonly PublicUrlGeneratorInterface $urlGenerator,
    ) {}

    public function handle(UploadImageCommand $command): UploadResultDto
    {
        $driver = $this->driverResolver->resolve();
        $stored = $driver->store($command->contents, $command->extension, $command->mimeType);

        $upload = $this->uploadRepository->save(new Upload(
            id: null,
            originalName: $command->originalName,
            path: $stored->path,
            disk: $stored->disk,
            mimeType: $command->mimeType,
            size: $command->size,
            uploadedBy: $command->uploadedBy,
        ));

        return new UploadResultDto(
            id: $upload->id,
            url: $this->urlGenerator->generate($upload->path, $upload->disk),
            path: $upload->path,
            filename: $stored->filename(),
            size: $upload->size,
        );
    }
}
