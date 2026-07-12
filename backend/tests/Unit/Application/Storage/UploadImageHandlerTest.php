<?php

namespace Tests\Unit\Application\Storage;

use App\Application\Storage\DTO\UploadImageCommand;
use App\Application\Storage\UploadImage\UploadImageHandler;
use App\Domain\Storage\Entities\Upload;
use App\Domain\Storage\Repositories\UploadRepositoryInterface;
use App\Domain\Storage\Services\PublicUrlGeneratorInterface;
use App\Domain\Storage\Services\StorageDriverInterface;
use App\Domain\Storage\Services\StorageDriverResolverInterface;
use App\Domain\Storage\ValueObjects\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UploadImageHandlerTest extends TestCase
{
    #[Test]
    public function handle_stores_file_and_persists_upload_record(): void
    {
        $driver = $this->createMock(StorageDriverInterface::class);
        $driver->method('store')->willReturn(new StoredFile(
            path: 'uploads/2026/07/abc.jpg',
            disk: 'local',
        ));

        $resolver = $this->createMock(StorageDriverResolverInterface::class);
        $resolver->method('resolve')->willReturn($driver);

        $repository = $this->createMock(UploadRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (Upload $upload) => $upload->originalName === 'photo.jpg'
                && $upload->disk === 'local'
                && $upload->size === 512))
            ->willReturn(new Upload(
                id: 1,
                originalName: 'photo.jpg',
                path: 'uploads/2026/07/abc.jpg',
                disk: 'local',
                mimeType: 'image/jpeg',
                size: 512,
                uploadedBy: 10,
            ));

        $urlGenerator = $this->createMock(PublicUrlGeneratorInterface::class);
        $urlGenerator->method('generate')
            ->with('uploads/2026/07/abc.jpg', 'local')
            ->willReturn('http://localhost:8000/storage/uploads/2026/07/abc.jpg');

        $handler = new UploadImageHandler($resolver, $repository, $urlGenerator);

        $result = $handler->handle(new UploadImageCommand(
            originalName: 'photo.jpg',
            contents: 'binary',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            size: 512,
            uploadedBy: 10,
        ));

        $this->assertSame(1, $result->id);
        $this->assertSame('abc.jpg', $result->filename);
        $this->assertSame(512, $result->size);
        $this->assertSame('http://localhost:8000/storage/uploads/2026/07/abc.jpg', $result->url);
    }
}
