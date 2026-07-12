<?php

namespace Tests\Unit\Domain\Storage\ValueObjects;

use App\Domain\Storage\ValueObjects\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoredFileTest extends TestCase
{
    #[Test]
    public function filename_returns_basename_of_path(): void
    {
        $file = new StoredFile(
            path: 'uploads/2026/07/abc123.jpg',
            disk: 'local',
        );

        $this->assertSame('abc123.jpg', $file->filename());
    }
}
