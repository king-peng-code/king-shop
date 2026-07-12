# M02 图片存储 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现 `POST /api/v1/admin/upload` 图片上传 API、`uploads` 元数据持久化、Local/OSS 双驱动及可替换的 `StorageDriverResolver`（首期硬编码 local）。

**Architecture:** Domain 定义 `Upload` 实体、`StorageDriverInterface` 与仓储接口；DB 仅存相对 `path`（不含域名）；`PublicUrlGenerator` 按 `config/storage.php` 在输出时拼接完整 URL；Infrastructure 实现 `LocalStorageDriver`（public disk）和 `OssStorageDriver`（S3 兼容）；Application 层 `UploadImageHandler` 编排驱动存储 + 仓储保存 + URL 生成；Http 层 Controller 仅做校验与响应。首期 `AppServiceProvider` 绑定 `HardcodedStorageDriverResolver`，`ConfigStorageDriverResolver` 实现但不绑定。

**Tech Stack:** PHP 8.4 · Laravel 12 · Laravel Sanctum · league/flysystem-aws-s3-v3 · PHPUnit 11 · SQLite `:memory:`（测试）

## Global Constraints

- PHP **8.4**（禁止 8.3 及以下及 8.5+）
- Laravel **12**（禁止其他主版本）
- API 前缀 `/api/v1/`
- 成功响应 `{ "code": 0, "message": "ok", "data": {} }`
- 422 验证错误 `{ "code": 422, "message": "...", "data": { "errors": {} } }`
- 鉴权 `auth:sanctum`（M02 不做角色检查）
- DDD 分层：Domain / Application / Infrastructure / Http
- TDD：先写失败测试，再实现
- DB 仅存相对 `path`，不存完整 URL
- 公开 URL 由 `PublicUrlGenerator` 按 `config/storage.php` 动态拼接
- 图片格式：jpg/png/webp，≤2MB
- 完成门槛：`docker compose exec backend php artisan test` 全部通过

---

### Task 1: Migration 与 Eloquent Model

**Files:**
- Create: `backend/database/migrations/2026_07_12_110000_create_uploads_table.php`
- Create: `backend/app/Infrastructure/Persistence/Eloquent/Models/UploadModel.php`

**Interfaces:**
- Produces: `UploadModel` Eloquent model，`$table = 'uploads'`，`$fillable = ['original_name', 'path', 'disk', 'mime_type', 'size', 'uploaded_by']`（无 `url` 列）

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('path', 500);
            $table->string('disk', 20);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
```

- [ ] **Step 2: Create Eloquent model**

```php
<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class UploadModel extends Model
{
    protected $table = 'uploads';

    protected $fillable = [
        'original_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
```

- [ ] **Step 3: Run migration**

Run: `docker compose exec backend php artisan migrate`
Expected: `uploads` table created

- [ ] **Step 4: Commit**

```bash
git add backend/database/migrations/2026_07_12_110000_create_uploads_table.php \
        backend/app/Infrastructure/Persistence/Eloquent/Models/UploadModel.php
git commit -m "feat(M02): add uploads migration and model"
```

---

### Task 2: Domain 层 — 实体、值对象、接口

**Files:**
- Create: `backend/app/Domain/Storage/Entities/Upload.php`
- Create: `backend/app/Domain/Storage/ValueObjects/StoredFile.php`
- Create: `backend/app/Domain/Storage/Repositories/UploadRepositoryInterface.php`
- Create: `backend/app/Domain/Storage/Services/StorageDriverInterface.php`
- Create: `backend/app/Domain/Storage/Services/StorageDriverResolverInterface.php`
- Create: `backend/app/Domain/Storage/Services/PublicUrlGeneratorInterface.php`
- Create: `backend/config/storage.php`
- Create: `backend/app/Infrastructure/Storage/ConfigPublicUrlGenerator.php`
- Test: `backend/tests/Unit/Domain/Storage/ValueObjects/StoredFileTest.php`
- Test: `backend/tests/Unit/Infrastructure/Storage/ConfigPublicUrlGeneratorTest.php`
- Create: `backend/app/Domain/Storage/Exceptions/StorageException.php`

**Interfaces:**
- Produces: `StoredFile` readonly VO with `path`, `disk`, `filename()`（无 `url`）
- Produces: `Upload` entity with `originalName`, `path`, `disk`, `mimeType`, `size`, `uploadedBy`, optional `id`（无 `url`）
- Produces: `PublicUrlGeneratorInterface::generate(string $path, string $disk): string`
- Produces: `StorageDriverInterface::store(string $contents, string $extension, string $mimeType): StoredFile`
- Produces: `StorageDriverResolverInterface::resolve(): StorageDriverInterface`
- Produces: `UploadRepositoryInterface::save(Upload $upload): Upload`
- Produces: `StorageException extends Exception`

- [ ] **Step 1: Write failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=StoredFileTest`
Expected: FAIL — class not found

- [ ] **Step 3: Implement Domain classes**

`StoredFile.php`:

```php
<?php

namespace App\Domain\Storage\ValueObjects;

readonly class StoredFile
{
    public function __construct(
        public string $path,
        public string $disk,
    ) {}

    public function filename(): string
    {
        return basename($this->path);
    }
}
```

`Upload.php`:

```php
<?php

namespace App\Domain\Storage\Entities;

final class Upload
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $originalName,
        public readonly string $path,
        public readonly string $disk,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly ?int $uploadedBy,
    ) {}
}
```

`StorageDriverInterface.php`:

```php
<?php

namespace App\Domain\Storage\Services;

use App\Domain\Storage\ValueObjects\StoredFile;

interface StorageDriverInterface
{
    public function store(string $contents, string $extension, string $mimeType): StoredFile;
}
```

`StorageDriverResolverInterface.php`:

```php
<?php

namespace App\Domain\Storage\Services;

interface StorageDriverResolverInterface
{
    public function resolve(): StorageDriverInterface;
}
```

`UploadRepositoryInterface.php`:

```php
<?php

namespace App\Domain\Storage\Repositories;

use App\Domain\Storage\Entities\Upload;

interface UploadRepositoryInterface
{
    public function save(Upload $upload): Upload;
}
```

`StorageException.php`:

```php
<?php

namespace App\Domain\Storage\Exceptions;

use Exception;

class StorageException extends Exception
{
}
```

`PublicUrlGeneratorInterface.php`:

```php
<?php

namespace App\Domain\Storage\Services;

interface PublicUrlGeneratorInterface
{
    public function generate(string $path, string $disk): string;
}
```

`config/storage.php`:

```php
<?php

return [
    'public_base_url' => env('STORAGE_PUBLIC_BASE_URL', env('APP_URL', 'http://localhost')),
    'oss_public_base_url' => env('STORAGE_OSS_PUBLIC_BASE_URL', ''),
];
```

`ConfigPublicUrlGenerator.php`:

```php
<?php

namespace App\Infrastructure\Storage;

use App\Domain\Storage\Services\PublicUrlGeneratorInterface;

class ConfigPublicUrlGenerator implements PublicUrlGeneratorInterface
{
    public function generate(string $path, string $disk): string
    {
        $path = ltrim($path, '/');

        return match ($disk) {
            'oss' => rtrim(config('storage.oss_public_base_url'), '/').'/'.$path,
            default => rtrim(config('storage.public_base_url'), '/').'/storage/'.$path,
        };
    }
}
```

`ConfigPublicUrlGeneratorTest.php`:

```php
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
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec backend php artisan test --filter='StoredFileTest|ConfigPublicUrlGeneratorTest'`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Storage/ \
        backend/app/Infrastructure/Storage/ConfigPublicUrlGenerator.php \
        backend/config/storage.php \
        backend/tests/Unit/Domain/Storage/ \
        backend/tests/Unit/Infrastructure/Storage/
git commit -m "feat(M02): add storage domain layer and PublicUrlGenerator"
```

---

### Task 3: LocalStorageDriver

**Files:**
- Create: `backend/app/Infrastructure/Storage/Drivers/LocalStorageDriver.php`
- Test: `backend/tests/Feature/Infrastructure/LocalStorageDriverTest.php`

**Interfaces:**
- Consumes: `StorageDriverInterface`, `StoredFile`
- Produces: `LocalStorageDriver::store()` writes to `public` disk, returns `StoredFile { path, disk }` only

- [ ] **Step 1: Write failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=LocalStorageDriverTest`
Expected: FAIL — class not found

- [ ] **Step 3: Implement LocalStorageDriver**

```php
<?php

namespace App\Infrastructure\Storage\Drivers;

use App\Domain\Storage\Exceptions\StorageException;
use App\Domain\Storage\Services\StorageDriverInterface;
use App\Domain\Storage\ValueObjects\StoredFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LocalStorageDriver implements StorageDriverInterface
{
    public function store(string $contents, string $extension, string $mimeType): StoredFile
    {
        $path = sprintf(
            'uploads/%s/%s.%s',
            now()->format('Y/m'),
            Str::uuid(),
            ltrim($extension, '.'),
        );

        $saved = Storage::disk('public')->put($path, $contents);

        if (! $saved) {
            throw new StorageException('Failed to store file on local disk.');
        }

        return new StoredFile(
            path: $path,
            disk: 'local',
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec backend php artisan test --filter=LocalStorageDriverTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Infrastructure/Storage/Drivers/LocalStorageDriver.php \
        backend/tests/Feature/Infrastructure/LocalStorageDriverTest.php
git commit -m "feat(M02): add LocalStorageDriver"
```

---

### Task 4: OssStorageDriver + Composer 依赖

**Files:**
- Modify: `backend/composer.json`（添加 `league/flysystem-aws-s3-v3`）
- Create: `backend/app/Infrastructure/Storage/Drivers/OssStorageDriver.php`
- Test: `backend/tests/Feature/Infrastructure/OssStorageDriverTest.php`

**Interfaces:**
- Consumes: `SystemConfigRepositoryInterface::findByGroupAndKey()` for `storage.oss.*` keys
- Produces: `OssStorageDriver::store()` writes to dynamic `oss` disk

- [ ] **Step 1: Add composer dependency**

在 `backend/composer.json` 的 `require` 中添加：

```json
"league/flysystem-aws-s3-v3": "^3.0"
```

Run: `docker compose exec backend composer require league/flysystem-aws-s3-v3:^3.0 --no-interaction`
Expected: Package installed

- [ ] **Step 2: Write failing test**

```php
<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Infrastructure\Storage\Drivers\OssStorageDriver;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OssStorageDriverTest extends TestCase
{
    #[Test]
    public function store_writes_file_to_s3_compatible_disk(): void
    {
        Storage::fake('oss_upload');

        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')->willReturnCallback(
            fn (string $group, string $key) => match ("{$group}.{$key}") {
                'storage.oss.bucket' => new SystemConfig('storage', 'oss.bucket', 'test-bucket', true),
                'storage.oss.endpoint' => new SystemConfig('storage', 'oss.endpoint', 'https://oss-cn-test.aliyuncs.com', true),
                'storage.oss.access_key' => new SystemConfig('storage', 'oss.access_key', 'test-key', true),
                'storage.oss.secret_key' => new SystemConfig('storage', 'oss.secret_key', 'test-secret', true),
                default => null,
            }
        );

        config([
            'filesystems.disks.oss_upload' => [
                'driver' => 's3',
                'key' => 'test-key',
                'secret' => 'test-secret',
                'region' => 'oss-cn-test',
                'bucket' => 'test-bucket',
                'url' => 'https://test-bucket.oss-cn-test.aliyuncs.com',
                'endpoint' => 'https://oss-cn-test.aliyuncs.com',
                'use_path_style_endpoint' => false,
                'throw' => true,
            ],
        ]);

        $driver = new OssStorageDriver($repository);
        $result = $driver->store('oss-content', 'png', 'image/png');

        $this->assertStringStartsWith('uploads/', $result->path);
        $this->assertSame('oss', $result->disk);
        Storage::disk('oss_upload')->assertExists($result->path);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=OssStorageDriverTest`
Expected: FAIL — class not found

- [ ] **Step 4: Implement OssStorageDriver**

```php
<?php

namespace App\Infrastructure\Storage\Drivers;

use App\Domain\Storage\Exceptions\StorageException;
use App\Domain\Storage\Services\StorageDriverInterface;
use App\Domain\Storage\ValueObjects\StoredFile;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OssStorageDriver implements StorageDriverInterface
{
    public function __construct(
        private readonly SystemConfigRepositoryInterface $configRepository,
    ) {}

    public function store(string $contents, string $extension, string $mimeType): StoredFile
    {
        $bucket = $this->configValue('oss.bucket');
        $endpoint = $this->configValue('oss.endpoint');
        $accessKey = $this->configValue('oss.access_key');
        $secretKey = $this->configValue('oss.secret_key');

        Config::set('filesystems.disks.oss_upload', [
            'driver' => 's3',
            'key' => $accessKey,
            'secret' => $secretKey,
            'region' => 'oss-cn-hangzhou',
            'bucket' => $bucket,
            'endpoint' => $endpoint,
            'url' => rtrim($endpoint, '/').'/'.$bucket,
            'use_path_style_endpoint' => false,
            'throw' => true,
        ]);

        $path = sprintf(
            'uploads/%s/%s.%s',
            now()->format('Y/m'),
            Str::uuid(),
            ltrim($extension, '.'),
        );

        $saved = Storage::disk('oss_upload')->put($path, $contents);

        if (! $saved) {
            throw new StorageException('Failed to store file on OSS disk.');
        }

        return new StoredFile(
            path: $path,
            disk: 'oss',
        );
    }

    private function configValue(string $key): string
    {
        $config = $this->configRepository->findByGroupAndKey('storage', $key);

        if ($config === null || $config->value === '') {
            throw new StorageException("Missing storage config: storage.{$key}");
        }

        return $config->value;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec backend php artisan test --filter=OssStorageDriverTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add backend/composer.json backend/composer.lock \
        backend/app/Infrastructure/Storage/Drivers/OssStorageDriver.php \
        backend/tests/Feature/Infrastructure/OssStorageDriverTest.php
git commit -m "feat(M02): add OssStorageDriver with S3-compatible driver"
```

---

### Task 5: StorageDriverResolver 实现

**Files:**
- Create: `backend/app/Infrastructure/Storage/Resolvers/HardcodedStorageDriverResolver.php`
- Create: `backend/app/Infrastructure/Storage/Resolvers/ConfigStorageDriverResolver.php`
- Test: `backend/tests/Feature/Infrastructure/ConfigStorageDriverResolverTest.php`

**Interfaces:**
- Consumes: `LocalStorageDriver`, `OssStorageDriver`, `SystemConfigRepositoryInterface`
- Produces: `HardcodedStorageDriverResolver::resolve()` always returns `LocalStorageDriver`
- Produces: `ConfigStorageDriverResolver::resolve()` reads `storage.driver` → local/oss

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Domain\Storage\Services\StorageDriverInterface;
use App\Infrastructure\Storage\Drivers\LocalStorageDriver;
use App\Infrastructure\Storage\Drivers\OssStorageDriver;
use App\Infrastructure\Storage\Resolvers\ConfigStorageDriverResolver;
use App\Infrastructure\Storage\Resolvers\HardcodedStorageDriverResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfigStorageDriverResolverTest extends TestCase
{
    #[Test]
    public function hardcoded_resolver_always_returns_local_driver(): void
    {
        $resolver = new HardcodedStorageDriverResolver(new LocalStorageDriver);

        $this->assertInstanceOf(LocalStorageDriver::class, $resolver->resolve());
    }

    #[Test]
    public function config_resolver_returns_oss_driver_when_configured(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')
            ->with('storage', 'driver')
            ->willReturn(new SystemConfig('storage', 'driver', 'oss', false));

        $resolver = new ConfigStorageDriverResolver(
            $repository,
            new LocalStorageDriver,
            $this->createMock(OssStorageDriver::class),
        );

        $this->assertInstanceOf(OssStorageDriver::class, $resolver->resolve());
    }

    #[Test]
    public function config_resolver_returns_local_driver_by_default(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('findByGroupAndKey')
            ->with('storage', 'driver')
            ->willReturn(new SystemConfig('storage', 'driver', 'local', false));

        $resolver = new ConfigStorageDriverResolver(
            $repository,
            new LocalStorageDriver,
            $this->createMock(OssStorageDriver::class),
        );

        $this->assertInstanceOf(LocalStorageDriver::class, $resolver->resolve());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=ConfigStorageDriverResolverTest`
Expected: FAIL — class not found

- [ ] **Step 3: Implement resolvers**

`HardcodedStorageDriverResolver.php`:

```php
<?php

namespace App\Infrastructure\Storage\Resolvers;

use App\Domain\Storage\Services\StorageDriverInterface;
use App\Domain\Storage\Services\StorageDriverResolverInterface;
use App\Infrastructure\Storage\Drivers\LocalStorageDriver;

class HardcodedStorageDriverResolver implements StorageDriverResolverInterface
{
    public function __construct(
        private readonly LocalStorageDriver $localDriver,
    ) {}

    public function resolve(): StorageDriverInterface
    {
        return $this->localDriver;
    }
}
```

`ConfigStorageDriverResolver.php`:

```php
<?php

namespace App\Infrastructure\Storage\Resolvers;

use App\Domain\Storage\Services\StorageDriverInterface;
use App\Domain\Storage\Services\StorageDriverResolverInterface;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Infrastructure\Storage\Drivers\LocalStorageDriver;
use App\Infrastructure\Storage\Drivers\OssStorageDriver;

class ConfigStorageDriverResolver implements StorageDriverResolverInterface
{
    public function __construct(
        private readonly SystemConfigRepositoryInterface $configRepository,
        private readonly LocalStorageDriver $localDriver,
        private readonly OssStorageDriver $ossDriver,
    ) {}

    public function resolve(): StorageDriverInterface
    {
        $config = $this->configRepository->findByGroupAndKey('storage', 'driver');
        $driver = $config?->value ?? 'local';

        return match ($driver) {
            'oss' => $this->ossDriver,
            default => $this->localDriver,
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec backend php artisan test --filter=ConfigStorageDriverResolverTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Infrastructure/Storage/Resolvers/ \
        backend/tests/Feature/Infrastructure/ConfigStorageDriverResolverTest.php
git commit -m "feat(M02): add storage driver resolvers"
```

---

### Task 6: EloquentUploadRepository

**Files:**
- Create: `backend/app/Infrastructure/Persistence/Eloquent/EloquentUploadRepository.php`
- Test: `backend/tests/Feature/Infrastructure/EloquentUploadRepositoryTest.php`

**Interfaces:**
- Consumes: `UploadModel`, `Upload` entity, `UploadRepositoryInterface`
- Produces: `EloquentUploadRepository::save(Upload): Upload` with assigned `id`

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\Storage\Entities\Upload;
use App\Infrastructure\Persistence\Eloquent\EloquentUploadRepository;
use App\Infrastructure\Persistence\Eloquent\Models\UploadModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentUploadRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function save_persists_upload_and_returns_entity_with_id(): void
    {
        $user = User::factory()->create();
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
            'disk' => 'local',
            'uploaded_by' => $user->id,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=EloquentUploadRepositoryTest`
Expected: FAIL — class not found

- [ ] **Step 3: Implement repository**

```php
<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Storage\Entities\Upload;
use App\Domain\Storage\Repositories\UploadRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UploadModel;

class EloquentUploadRepository implements UploadRepositoryInterface
{
    public function save(Upload $upload): Upload
    {
        $model = UploadModel::query()->create([
            'original_name' => $upload->originalName,
            'path' => $upload->path,
            'disk' => $upload->disk,
            'mime_type' => $upload->mimeType,
            'size' => $upload->size,
            'uploaded_by' => $upload->uploadedBy,
        ]);

        return $this->toEntity($model);
    }

    private function toEntity(UploadModel $model): Upload
    {
        return new Upload(
            id: $model->id,
            originalName: $model->original_name,
            path: $model->path,
            disk: $model->disk,
            mimeType: $model->mime_type,
            size: $model->size,
            uploadedBy: $model->uploaded_by,
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec backend php artisan test --filter=EloquentUploadRepositoryTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Infrastructure/Persistence/Eloquent/EloquentUploadRepository.php \
        backend/tests/Feature/Infrastructure/EloquentUploadRepositoryTest.php
git commit -m "feat(M02): add EloquentUploadRepository"
```

---

### Task 7: UploadImageHandler（Application 层）

**Files:**
- Create: `backend/app/Application/Storage/DTO/UploadImageCommand.php`
- Create: `backend/app/Application/Storage/DTO/UploadResultDto.php`
- Create: `backend/app/Application/Storage/UploadImage/UploadImageHandler.php`
- Test: `backend/tests/Unit/Application/Storage/UploadImageHandlerTest.php`

**Interfaces:**
- Consumes: `StorageDriverResolverInterface`, `UploadRepositoryInterface`, `PublicUrlGeneratorInterface`, `UploadImageCommand`
- Produces: `UploadImageHandler::handle(UploadImageCommand): UploadResultDto`（`url` 由 `PublicUrlGenerator` 生成）

- [ ] **Step 1: Write failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=UploadImageHandlerTest`
Expected: FAIL — class not found

- [ ] **Step 3: Implement Application layer**

`UploadImageCommand.php`:

```php
<?php

namespace App\Application\Storage\DTO;

readonly class UploadImageCommand
{
    public function __construct(
        public string $originalName,
        public string $contents,
        public string $extension,
        public string $mimeType,
        public int $size,
        public ?int $uploadedBy,
    ) {}
}
```

`UploadResultDto.php`:

```php
<?php

namespace App\Application\Storage\DTO;

readonly class UploadResultDto
{
    public function __construct(
        public int $id,
        public string $url,
        public string $path,
        public string $filename,
        public int $size,
    ) {}
}
```

`UploadImageHandler.php`:

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec backend php artisan test --filter=UploadImageHandlerTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Application/Storage/ \
        backend/tests/Unit/Application/Storage/
git commit -m "feat(M02): add UploadImageHandler"
```

---

### Task 8: Http 层 — Controller、Request、Resource、路由

**Files:**
- Create: `backend/app/Http/Controllers/Admin/UploadController.php`
- Create: `backend/app/Http/Requests/Admin/UploadImageRequest.php`
- Create: `backend/app/Http/Resources/Admin/UploadResource.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Exceptions/ApiExceptionHandler.php`（注册 `StorageException`）
- Test: `backend/tests/Feature/Admin/UploadApiTest.php`

**Interfaces:**
- Consumes: `UploadImageHandler`, `UploadImageCommand`, `UploadResource`
- Produces: `POST /api/v1/admin/upload` endpoint

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Infrastructure\Persistence\Eloquent\Models\UploadModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UploadApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->user = User::factory()->create();
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
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=UploadApiTest`
Expected: FAIL — route not found or 404

- [ ] **Step 3: Implement Http layer**

`UploadImageRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }
}
```

`UploadResource.php`:

```php
<?php

namespace App\Http\Resources\Admin;

use App\Application\Storage\DTO\UploadResultDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UploadResultDto */
class UploadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'path' => $this->path,
            'filename' => $this->filename,
            'size' => $this->size,
        ];
    }
}
```

`UploadController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Application\Storage\DTO\UploadImageCommand;
use App\Application\Storage\UploadImage\UploadImageHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadImageRequest;
use App\Http\Resources\Admin\UploadResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class UploadController extends Controller
{
    public function store(
        UploadImageRequest $request,
        UploadImageHandler $handler,
    ): JsonResponse {
        $file = $request->file('file');

        $result = $handler->handle(new UploadImageCommand(
            originalName: $file->getClientOriginalName(),
            contents: $file->get(),
            extension: $file->getClientOriginalExtension() ?: $file->extension(),
            mimeType: $file->getMimeType() ?? 'application/octet-stream',
            size: $file->getSize(),
            uploadedBy: $request->user()?->id,
        ));

        return ApiResponse::success(new UploadResource($result));
    }
}
```

`routes/api.php` 添加：

```php
use App\Http\Controllers\Admin\UploadController;

Route::middleware('auth:sanctum')->prefix('admin')->group(function (): void {
    // ... existing routes ...
    Route::post('/upload', [UploadController::class, 'store']);
});
```

`ApiExceptionHandler.php` 在 `BusinessException` 判断后添加：

```php
use App\Domain\Storage\Exceptions\StorageException;

if ($e instanceof StorageException) {
    return ApiResponse::error(2001, $e->getMessage(), null, 500);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec backend php artisan test --filter=UploadApiTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/Admin/UploadController.php \
        backend/app/Http/Requests/Admin/UploadImageRequest.php \
        backend/app/Http/Resources/Admin/UploadResource.php \
        backend/routes/api.php \
        backend/app/Exceptions/ApiExceptionHandler.php \
        backend/tests/Feature/Admin/UploadApiTest.php
git commit -m "feat(M02): add admin upload API endpoint"
```

---

### Task 9: ServiceProvider 绑定 + storage:link

**Files:**
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Modify: `backend/docker/entrypoint.sh`

**Interfaces:**
- Binds: `UploadRepositoryInterface` → `EloquentUploadRepository`
- Binds: `StorageDriverResolverInterface` → `HardcodedStorageDriverResolver`（首期）
- Registers: `LocalStorageDriver`, `OssStorageDriver` as singletons

- [ ] **Step 1: Update AppServiceProvider**

```php
use App\Domain\Storage\Repositories\UploadRepositoryInterface;
use App\Domain\Storage\Services\PublicUrlGeneratorInterface;
use App\Domain\Storage\Services\StorageDriverResolverInterface;
use App\Infrastructure\Persistence\Eloquent\EloquentUploadRepository;
use App\Infrastructure\Storage\ConfigPublicUrlGenerator;
use App\Infrastructure\Storage\Drivers\LocalStorageDriver;
use App\Infrastructure\Storage\Drivers\OssStorageDriver;
use App\Infrastructure\Storage\Resolvers\HardcodedStorageDriverResolver;

public function register(): void
{
    // ... existing M01 bindings ...

    $this->app->singleton(LocalStorageDriver::class);
    $this->app->singleton(OssStorageDriver::class);
    $this->app->bind(PublicUrlGeneratorInterface::class, ConfigPublicUrlGenerator::class);
    $this->app->bind(UploadRepositoryInterface::class, EloquentUploadRepository::class);
    $this->app->bind(StorageDriverResolverInterface::class, HardcodedStorageDriverResolver::class);
}
```

- [ ] **Step 2: Add storage:link to entrypoint**

在 `backend/docker/entrypoint.sh` 的 `php artisan migrate --force` 后添加：

```sh
php artisan storage:link --force 2>/dev/null || true
```

在 `backend/.env.example` 添加：

```env
STORAGE_PUBLIC_BASE_URL=
STORAGE_OSS_PUBLIC_BASE_URL=
```

- [ ] **Step 3: Run full test suite**

Run: `docker compose exec backend php artisan test`
Expected: ALL PASS

- [ ] **Step 4: Commit**

```bash
git add backend/app/Providers/AppServiceProvider.php \
        backend/docker/entrypoint.sh \
        backend/.env.example
git commit -m "feat(M02): wire storage bindings and storage:link"
```

---

### Task 10: 更新模块状态 + 验收记录

**Files:**
- Modify: `docs/superpowers/specs/2026-07-12-internal-mall-design.md`（M02 状态 → 进行中/完成）
- Create: `docs/superpowers/records/2026-07-12-M02-image-storage.md`

- [ ] **Step 1: Update module status table**

将 `2026-07-12-internal-mall-design.md` §7.2 中 M02 状态更新为 `✅ 已完成`。

- [ ] **Step 2: Write execution record**

记录变更文件清单、验收 checklist 结果、`php artisan test` 输出摘要。

- [ ] **Step 3: Commit**

```bash
git add docs/superpowers/
git commit -m "docs(M02): add execution record and update module status"
```

---

## Plan Self-Review

| Spec 要求 | 对应 Task |
|---|---|
| uploads 表仅存相对 path | Task 1, 6 |
| PublicUrlGenerator 动态拼接 url | Task 2, 7, 8 |
| config/storage.php | Task 2 |
| LocalStorageDriver | Task 3 |
| OssStorageDriver S3 兼容 | Task 4 |
| HardcodedStorageDriverResolver 首期 | Task 5, 9 |
| ConfigStorageDriverResolver 就绪 | Task 5 |
| POST /admin/upload API | Task 8 |
| jpg/png/webp ≤2MB | Task 8 (UploadImageRequest) |
| uploads 表返回 id | Task 7, 8 |
| DDD 四层 + TDD | 全部 Tasks |
| storage:link | Task 9 |
| php artisan test 全绿 | Task 9 Step 3 |

无 placeholder，类型签名跨 Task 一致。
