<?php

namespace Tests\Unit\Application\Catalog;

use App\Application\Catalog\Services\ProductImageResolver;
use App\Infrastructure\Persistence\Eloquent\Models\UploadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductImageResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLocalStoragePublicBaseUrl();
    }

    #[Test]
    public function resolves_url_with_local_disk_by_default(): void
    {
        $resolver = app(ProductImageResolver::class);
        $url = $resolver->resolveUrl('uploads/2026/07/a.jpg', null);
        $this->assertSame('http://localhost:8000/storage/uploads/2026/07/a.jpg', $url);
    }

    #[Test]
    public function resolves_url_using_upload_disk_when_upload_id_present(): void
    {
        $upload = UploadModel::factory()->create([
            'path' => 'uploads/2026/07/b.jpg',
            'disk' => 'local',
        ]);
        $resolver = app(ProductImageResolver::class);
        $url = $resolver->resolveUrl('uploads/2026/07/b.jpg', $upload->id);
        $this->assertStringContainsString('/storage/uploads/2026/07/b.jpg', $url);
    }
}
