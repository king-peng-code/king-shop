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
            ->method('findByMd5')
            ->with(md5('binary'))
            ->willReturn(null);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (Upload $upload) => $upload->originalName === 'photo.jpg'
                && $upload->disk === 'local'
                && $upload->size === 512
                && $upload->md5 === md5('binary')))
            ->willReturn(new Upload(
                id: 1,
                originalName: 'photo.jpg',
                path: 'uploads/2026/07/abc.jpg',
                disk: 'local',
                mimeType: 'image/jpeg',
                size: 512,
                md5: md5('binary'),
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

    #[Test]
    public function handle_returns_existing_upload_when_md5_matches(): void
    {
        $existing = new Upload(
            id: 5,
            originalName: 'old.jpg',
            path: 'uploads/2026/07/existing.jpg',
            disk: 'local',
            mimeType: 'image/jpeg',
            size: 800,
            md5: md5('binary'),
            uploadedBy: 3,
        );

        $resolver = $this->createMock(StorageDriverResolverInterface::class);
        $resolver->expects($this->never())->method('resolve');

        $repository = $this->createMock(UploadRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findByMd5')
            ->with(md5('binary'))
            ->willReturn($existing);
        $repository->expects($this->never())->method('save');

        $urlGenerator = $this->createMock(PublicUrlGeneratorInterface::class);
        $urlGenerator->method('generate')
            ->with('uploads/2026/07/existing.jpg', 'local')
            ->willReturn('http://localhost:8000/storage/uploads/2026/07/existing.jpg');

        $handler = new UploadImageHandler($resolver, $repository, $urlGenerator);

        $result = $handler->handle(new UploadImageCommand(
            originalName: 'photo.jpg',
            contents: 'binary',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            size: 512,
            uploadedBy: 10,
        ));

        $this->assertSame(5, $result->id);
        $this->assertSame('existing.jpg', $result->filename);
        $this->assertSame(800, $result->size);
        $this->assertSame('http://localhost:8000/storage/uploads/2026/07/existing.jpg', $result->url);
    }
}
