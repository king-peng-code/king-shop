<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\Storage\Repositories\UploadRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UploadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentUploadRepositoryFindByIdTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function find_by_id_returns_upload_entity(): void
    {
        $model = UploadModel::factory()->create(['path' => 'uploads/2026/07/test.jpg']);
        $repo = app(UploadRepositoryInterface::class);
        $upload = $repo->findById($model->id);
        $this->assertNotNull($upload);
        $this->assertSame('uploads/2026/07/test.jpg', $upload->path);
    }
}
