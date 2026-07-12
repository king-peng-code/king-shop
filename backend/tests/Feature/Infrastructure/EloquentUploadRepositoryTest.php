<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\Storage\Entities\Upload;
use App\Infrastructure\Persistence\Eloquent\EloquentUploadRepository;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentUploadRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function save_persists_upload_and_returns_entity_with_id(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentUploadRepository;

        $entity = new Upload(
            id: null,
            originalName: 'photo.jpg',
            path: 'uploads/2026/07/test.jpg',
            disk: 'local',
            mimeType: 'image/jpeg',
            size: 1024,
            uploadedBy: $user->id,
        );

        $saved = $repository->save($entity);

        $this->assertNotNull($saved->id);
        $this->assertDatabaseHas('uploads', [
            'id' => $saved->id,
            'original_name' => 'photo.jpg',
            'path' => 'uploads/2026/07/test.jpg',
            'disk' => 'local',
            'uploaded_by' => $user->id,
        ]);
    }
}
