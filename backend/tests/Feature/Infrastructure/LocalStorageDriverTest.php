<?php

namespace Tests\Feature\Infrastructure;

use App\Infrastructure\Storage\Drivers\LocalStorageDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocalStorageDriverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function store_writes_file_to_public_disk(): void
    {
        Storage::fake('public');

        $driver = new LocalStorageDriver;
        $result = $driver->store('fake-image-content', 'jpg', 'image/jpeg');

        $this->assertStringStartsWith('uploads/', $result->path);
        $this->assertStringEndsWith('.jpg', $result->path);
        $this->assertSame('local', $result->disk);
        Storage::disk('public')->assertExists($result->path);
    }
}
