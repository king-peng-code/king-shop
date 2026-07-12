<?php

namespace Tests\Feature\Admin;

use App\Infrastructure\Persistence\Eloquent\Models\UploadModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UploadApiTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->user = UserModel::factory()->admin()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    #[Test]
    public function unauthenticated_upload_returns_401(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100)->size(100);

        $this->postJson('/api/v1/admin/upload', ['file' => $file])
            ->assertUnauthorized();
    }

    #[Test]
    public function invalid_file_type_returns_422(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->withToken($this->token)
            ->postJson('/api/v1/admin/upload', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    #[Test]
    public function upload_returns_id_url_and_persists_record(): void
    {
        config(['storage.public_base_url' => 'http://localhost:8000']);

        $file = UploadedFile::fake()->image('photo.jpg', 200, 200)->size(200);

        $response = $this->withToken($this->token)
            ->post('/api/v1/admin/upload', ['file' => $file], [
                'Accept' => 'application/json',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonStructure([
                'data' => ['id', 'url', 'path', 'filename', 'size'],
            ]);

        $upload = UploadModel::find($response->json('data.id'));
        $this->assertNotNull($upload);
        $this->assertSame('photo.jpg', $upload->original_name);
        $this->assertSame($this->user->id, $upload->uploaded_by);
        $this->assertStringStartsWith('uploads/', $upload->path);
        $this->assertStringStartsWith('http://localhost:8000/storage/', $response->json('data.url'));
        Storage::disk('public')->assertExists($upload->path);
    }

    #[Test]
    public function employee_token_cannot_upload(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee']);
        $token = $user->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100)->size(100);

        $this->withToken($token)
            ->postJson('/api/v1/admin/upload', ['file' => $file])
            ->assertForbidden();
    }
}
