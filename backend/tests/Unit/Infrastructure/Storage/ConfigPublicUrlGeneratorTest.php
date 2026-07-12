<?php

namespace Tests\Unit\Infrastructure\Storage;

use App\Infrastructure\Storage\ConfigPublicUrlGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfigPublicUrlGeneratorTest extends TestCase
{
    #[Test]
    public function local_disk_prepends_public_base_url_with_storage_prefix(): void
    {
        config(['storage.public_base_url' => 'http://localhost:8000']);

        $generator = new ConfigPublicUrlGenerator;
        $url = $generator->generate('uploads/2026/07/abc.jpg', 'local');

        $this->assertSame(
            'http://localhost:8000/storage/uploads/2026/07/abc.jpg',
            $url,
        );
    }

    #[Test]
    public function oss_disk_prepends_oss_public_base_url(): void
    {
        config(['storage.oss_public_base_url' => 'https://cdn.example.com']);

        $generator = new ConfigPublicUrlGenerator;
        $url = $generator->generate('uploads/2026/07/abc.jpg', 'oss');

        $this->assertSame(
            'https://cdn.example.com/uploads/2026/07/abc.jpg',
            $url,
        );
    }
}
