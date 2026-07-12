# M01 系统配置管理 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现 `system_configs` 加密存储、DDD 分层读写服务，以及 `GET/PUT /api/v1/admin/configs` 批量配置 API（Sanctum 鉴权）。

**Architecture:** Domain 定义 `SystemConfig` 实体与仓储/加密接口；Infrastructure 用 Eloquent + Laravel `Crypt` 实现持久化与加解密；Application 层 Handler 编排脱敏与批量更新；Http 层 Controller 仅做校验与响应封装。敏感项通过 DB `is_sensitive` 字段标识，GET 脱敏、PUT 遇 `****` 跳过。

**Tech Stack:** PHP 8.4 · Laravel 12 · Laravel Sanctum · PHPUnit 11 · SQLite `:memory:`（测试）

## Global Constraints

- PHP **8.4**（禁止 8.3 及以下及 8.5+）
- Laravel **12**（禁止其他主版本）
- API 前缀 `/api/v1/`
- 成功响应 `{ "code": 0, "message": "ok", "data": {} }`
- 422 验证错误 `{ "code": 422, "message": "...", "data": { "errors": {} } }`
- 鉴权 `auth:sanctum`（M01 不做角色检查）
- DDD 分层：Domain / Application / Infrastructure / Http
- TDD：先写失败测试，再实现
- 完成门槛：`docker compose exec backend php artisan test` 全部通过

---

### Task 1: Migration 与 Eloquent Model

**Files:**
- Create: `backend/database/migrations/2026_07_12_100000_create_system_configs_table.php`
- Create: `backend/app/Infrastructure/Persistence/Eloquent/Models/SystemConfigModel.php`

**Interfaces:**
- Produces: `SystemConfigModel` Eloquent model，`$table = 'system_configs'`，`$fillable = ['group', 'key', 'value', 'is_sensitive', 'description']`

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
        Schema::create('system_configs', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50);
            $table->string('key', 100);
            $table->text('value');
            $table->boolean('is_sensitive')->default(false);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_configs');
    }
};
```

- [ ] **Step 2: Create Eloquent model**

```php
<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class SystemConfigModel extends Model
{
    protected $table = 'system_configs';

    protected $fillable = [
        'group',
        'key',
        'value',
        'is_sensitive',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
        ];
    }
}
```

- [ ] **Step 3: Run migration in test environment**

Run: `docker compose exec backend php artisan migrate --env=testing`
Expected: `system_configs` table created

- [ ] **Step 4: Commit**

```bash
git add backend/database/migrations/2026_07_12_100000_create_system_configs_table.php \
        backend/app/Infrastructure/Persistence/Eloquent/Models/SystemConfigModel.php
git commit -m "feat(M01): add system_configs migration and model"
```

---

### Task 2: Domain 实体与接口

**Files:**
- Create: `backend/app/Domain/SystemConfig/Entities/SystemConfig.php`
- Create: `backend/app/Domain/SystemConfig/Repositories/SystemConfigRepositoryInterface.php`
- Create: `backend/app/Domain/SystemConfig/Services/ConfigEncryptionInterface.php`
- Create: `backend/app/Domain/SystemConfig/Exceptions/ConfigDecryptionException.php`
- Test: `backend/tests/Unit/Domain/SystemConfig/Entities/SystemConfigTest.php`

**Interfaces:**
- Produces: `SystemConfig` entity — `group`, `key`, `value`, `isSensitive`, `description`; method `displayValue(): string`
- Produces: `SystemConfigRepositoryInterface`:
  - `all(): array` — returns `SystemConfig[]`
  - `findByGroupAndKey(string $group, string $key): ?SystemConfig`
  - `updateValue(string $group, string $key, string $plainValue): void`
  - `exists(string $group, string $key): bool`
- Produces: `ConfigEncryptionInterface`:
  - `encrypt(string $plainText): string`
  - `decrypt(string $cipherText): string`
- Produces: `ConfigDecryptionException extends BusinessException` — httpStatus 500

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Domain\SystemConfig\Entities;

use App\Domain\SystemConfig\Entities\SystemConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SystemConfigTest extends TestCase
{
    #[Test]
    public function non_sensitive_config_returns_plain_display_value(): void
    {
        $config = new SystemConfig(
            group: 'app',
            key: 'name',
            value: '内部下午茶',
            isSensitive: false,
            description: '商城名称',
        );

        $this->assertSame('内部下午茶', $config->displayValue());
    }

    #[Test]
    public function sensitive_config_with_value_is_masked(): void
    {
        $config = new SystemConfig(
            group: 'payment',
            key: 'wechat.mch_id',
            value: '1234567890',
            isSensitive: true,
            description: '微信商户号',
        );

        $this->assertSame('****', $config->displayValue());
    }

    #[Test]
    public function sensitive_config_with_empty_value_returns_empty_string(): void
    {
        $config = new SystemConfig(
            group: 'payment',
            key: 'wechat.mch_id',
            value: '',
            isSensitive: true,
            description: '微信商户号',
        );

        $this->assertSame('', $config->displayValue());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=SystemConfigTest`
Expected: FAIL — class `SystemConfig` not found

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Domain\SystemConfig\Entities;

final class SystemConfig
{
    public const MASK_PLACEHOLDER = '****';

    public function __construct(
        public readonly string $group,
        public readonly string $key,
        public readonly string $value,
        public readonly bool $isSensitive,
        public readonly ?string $description = null,
    ) {}

    public function displayValue(): string
    {
        if ($this->isSensitive && $this->value !== '') {
            return self::MASK_PLACEHOLDER;
        }

        return $this->value;
    }
}
```

```php
<?php

namespace App\Domain\SystemConfig\Repositories;

use App\Domain\SystemConfig\Entities\SystemConfig;

interface SystemConfigRepositoryInterface
{
    /** @return SystemConfig[] */
    public function all(): array;

    public function findByGroupAndKey(string $group, string $key): ?SystemConfig;

    public function updateValue(string $group, string $key, string $plainValue): void;

    public function exists(string $group, string $key): bool;
}
```

```php
<?php

namespace App\Domain\SystemConfig\Services;

interface ConfigEncryptionInterface
{
    public function encrypt(string $plainText): string;

    public function decrypt(string $cipherText): string;
}
```

```php
<?php

namespace App\Domain\SystemConfig\Exceptions;

use App\Exceptions\BusinessException;

class ConfigDecryptionException extends BusinessException
{
    public function __construct(string $message = 'Failed to decrypt config value')
    {
        parent::__construct(500, $message, 500);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec backend php artisan test --filter=SystemConfigTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/SystemConfig/ \
        backend/tests/Unit/Domain/SystemConfig/
git commit -m "feat(M01): add SystemConfig domain entity and interfaces"
```

---

### Task 3: Laravel 加密适配器

**Files:**
- Create: `backend/app/Infrastructure/Encryption/LaravelConfigEncryption.php`
- Test: `backend/tests/Unit/Infrastructure/Encryption/LaravelConfigEncryptionTest.php`

**Interfaces:**
- Consumes: `ConfigEncryptionInterface`
- Produces: `LaravelConfigEncryption::encrypt(string): string`, `decrypt(string): string`
- Produces: decrypt failure throws `ConfigDecryptionException`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Infrastructure\Encryption;

use App\Domain\SystemConfig\Exceptions\ConfigDecryptionException;
use App\Infrastructure\Encryption\LaravelConfigEncryption;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LaravelConfigEncryptionTest extends TestCase
{
    #[Test]
    public function encrypt_and_decrypt_round_trip(): void
    {
        $encryption = new LaravelConfigEncryption;

        $cipher = $encryption->encrypt('内部下午茶');
        $plain = $encryption->decrypt($cipher);

        $this->assertNotSame('内部下午茶', $cipher);
        $this->assertSame('内部下午茶', $plain);
    }

    #[Test]
    public function decrypt_fails_with_different_app_key(): void
    {
        $encryption = new LaravelConfigEncryption;
        $cipher = $encryption->encrypt('secret-value');

        Config::set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $this->expectException(ConfigDecryptionException::class);
        $encryption->decrypt($cipher);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=LaravelConfigEncryptionTest`
Expected: FAIL — class not found

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Infrastructure\Encryption;

use App\Domain\SystemConfig\Exceptions\ConfigDecryptionException;
use App\Domain\SystemConfig\Services\ConfigEncryptionInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class LaravelConfigEncryption implements ConfigEncryptionInterface
{
    public function encrypt(string $plainText): string
    {
        return Crypt::encryptString($plainText);
    }

    public function decrypt(string $cipherText): string
    {
        try {
            return Crypt::decryptString($cipherText);
        } catch (DecryptException) {
            throw new ConfigDecryptionException;
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec backend php artisan test --filter=LaravelConfigEncryptionTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add backend/app/Infrastructure/Encryption/ \
        backend/tests/Unit/Infrastructure/Encryption/
git commit -m "feat(M01): add LaravelConfigEncryption adapter"
```

---

### Task 4: Eloquent 仓储实现

**Files:**
- Create: `backend/app/Infrastructure/Persistence/Eloquent/EloquentSystemConfigRepository.php`
- Test: `backend/tests/Feature/Infrastructure/EloquentSystemConfigRepositoryTest.php`

**Interfaces:**
- Consumes: `SystemConfigModel`, `ConfigEncryptionInterface`, `SystemConfig`, `SystemConfigRepositoryInterface`
- Produces: full repository implementation with encrypt-on-write, decrypt-on-read

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Infrastructure\Encryption\LaravelConfigEncryption;
use App\Infrastructure\Persistence\Eloquent\EloquentSystemConfigRepository;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentSystemConfigRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private SystemConfigRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentSystemConfigRepository(new LaravelConfigEncryption);
    }

    #[Test]
    public function all_returns_decrypted_configs(): void
    {
        SystemConfigModel::create([
            'group' => 'app',
            'key' => 'name',
            'value' => (new LaravelConfigEncryption)->encrypt('内部下午茶'),
            'is_sensitive' => false,
            'description' => '商城名称',
        ]);

        $configs = $this->repository->all();

        $this->assertCount(1, $configs);
        $this->assertInstanceOf(SystemConfig::class, $configs[0]);
        $this->assertSame('内部下午茶', $configs[0]->value);
    }

    #[Test]
    public function value_is_stored_encrypted_in_database(): void
    {
        $this->repository->updateValue('app', 'name', '内部下午茶');

        $raw = SystemConfigModel::where('group', 'app')->where('key', 'name')->first();

        $this->assertNotNull($raw);
        $this->assertNotSame('内部下午茶', $raw->value);
    }

    #[Test]
    public function find_by_group_and_key_returns_null_when_missing(): void
    {
        $this->assertNull($this->repository->findByGroupAndKey('app', 'missing'));
    }

    #[Test]
    public function exists_returns_true_for_seeded_key(): void
    {
        SystemConfigModel::create([
            'group' => 'app',
            'key' => 'name',
            'value' => (new LaravelConfigEncryption)->encrypt('x'),
            'is_sensitive' => false,
        ]);

        $this->assertTrue($this->repository->exists('app', 'name'));
        $this->assertFalse($this->repository->exists('app', 'missing'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=EloquentSystemConfigRepositoryTest`
Expected: FAIL — class not found

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Domain\SystemConfig\Services\ConfigEncryptionInterface;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;

class EloquentSystemConfigRepository implements SystemConfigRepositoryInterface
{
    public function __construct(
        private readonly ConfigEncryptionInterface $encryption,
    ) {}

    public function all(): array
    {
        return SystemConfigModel::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->map(fn (SystemConfigModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findByGroupAndKey(string $group, string $key): ?SystemConfig
    {
        $model = SystemConfigModel::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function updateValue(string $group, string $key, string $plainValue): void
    {
        SystemConfigModel::query()
            ->where('group', $group)
            ->where('key', $key)
            ->update(['value' => $this->encryption->encrypt($plainValue)]);
    }

    public function exists(string $group, string $key): bool
    {
        return SystemConfigModel::query()
            ->where('group', $group)
            ->where('key', $key)
            ->exists();
    }

    private function toEntity(SystemConfigModel $model): SystemConfig
    {
        return new SystemConfig(
            group: $model->group,
            key: $model->key,
            value: $this->encryption->decrypt($model->value),
            isSensitive: $model->is_sensitive,
            description: $model->description,
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec backend php artisan test --filter=EloquentSystemConfigRepositoryTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add backend/app/Infrastructure/Persistence/Eloquent/EloquentSystemConfigRepository.php \
        backend/tests/Feature/Infrastructure/
git commit -m "feat(M01): add EloquentSystemConfigRepository"
```

---

### Task 5: SystemConfigSeeder

**Files:**
- Create: `backend/database/seeders/SystemConfigSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Produces: 13 default config rows per design spec, values encrypted before insert

- [ ] **Step 1: Create seeder**

```php
<?php

namespace Database\Seeders;

use App\Infrastructure\Encryption\LaravelConfigEncryption;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use Illuminate\Database\Seeder;

class SystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        $encryption = new LaravelConfigEncryption;

        $configs = [
            ['group' => 'app', 'key' => 'name', 'value' => '内部下午茶', 'is_sensitive' => false, 'description' => '商城名称'],
            ['group' => 'order', 'key' => 'auto_cancel_minutes', 'value' => '30', 'is_sensitive' => false, 'description' => '未支付自动取消（分钟）'],
            ['group' => 'payment', 'key' => 'provider', 'value' => 'alipay_sandbox', 'is_sensitive' => false, 'description' => '支付渠道'],
            ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '', 'is_sensitive' => true, 'description' => '微信商户号'],
            ['group' => 'payment', 'key' => 'wechat.api_key', 'value' => '', 'is_sensitive' => true, 'description' => '微信 API 密钥'],
            ['group' => 'payment', 'key' => 'wechat.cert', 'value' => '', 'is_sensitive' => true, 'description' => '微信商户证书'],
            ['group' => 'payment', 'key' => 'alipay.app_id', 'value' => '', 'is_sensitive' => true, 'description' => '支付宝 App ID'],
            ['group' => 'payment', 'key' => 'alipay.private_key', 'value' => '', 'is_sensitive' => true, 'description' => '支付宝私钥'],
            ['group' => 'storage', 'key' => 'driver', 'value' => 'local', 'is_sensitive' => false, 'description' => '存储驱动'],
            ['group' => 'storage', 'key' => 'oss.bucket', 'value' => '', 'is_sensitive' => true, 'description' => 'OSS Bucket'],
            ['group' => 'storage', 'key' => 'oss.endpoint', 'value' => '', 'is_sensitive' => true, 'description' => 'OSS Endpoint'],
            ['group' => 'storage', 'key' => 'oss.access_key', 'value' => '', 'is_sensitive' => true, 'description' => 'OSS Access Key'],
            ['group' => 'storage', 'key' => 'oss.secret_key', 'value' => '', 'is_sensitive' => true, 'description' => 'OSS Secret Key'],
        ];

        foreach ($configs as $config) {
            SystemConfigModel::updateOrCreate(
                ['group' => $config['group'], 'key' => $config['key']],
                [
                    'value' => $encryption->encrypt($config['value']),
                    'is_sensitive' => $config['is_sensitive'],
                    'description' => $config['description'],
                ],
            );
        }
    }
}
```

- [ ] **Step 2: Register in DatabaseSeeder**

在 `DatabaseSeeder::run()` 末尾添加：

```php
$this->call(SystemConfigSeeder::class);
```

- [ ] **Step 3: Verify seeder runs**

Run: `docker compose exec backend php artisan db:seed --class=SystemConfigSeeder`
Expected: 13 rows in `system_configs`, `value` column is ciphertext

- [ ] **Step 4: Commit**

```bash
git add backend/database/seeders/
git commit -m "feat(M01): add SystemConfigSeeder with default configs"
```

---

### Task 6: GetSystemConfigsHandler

**Files:**
- Create: `backend/app/Application/SystemConfig/ConfigGroupLabels.php`
- Create: `backend/app/Application/SystemConfig/GetSystemConfigs/GetSystemConfigsHandler.php`
- Test: `backend/tests/Unit/Application/SystemConfig/GetSystemConfigsHandlerTest.php`

**Interfaces:**
- Consumes: `SystemConfigRepositoryInterface`, `SystemConfig::displayValue()`, `ConfigGroupLabels::MAP`
- Produces: `GetSystemConfigsHandler::handle(): array` — shape `{ groups: [{ name, label, items: [{ key, value, is_sensitive, description }] }] }`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Application\SystemConfig;

use App\Application\SystemConfig\GetSystemConfigs\GetSystemConfigsHandler;
use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GetSystemConfigsHandlerTest extends TestCase
{
    #[Test]
    public function handle_groups_configs_and_masks_sensitive_values(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->method('all')->willReturn([
            new SystemConfig('app', 'name', '内部下午茶', false, '商城名称'),
            new SystemConfig('payment', 'wechat.mch_id', '1234567890', true, '微信商户号'),
        ]);

        $handler = new GetSystemConfigsHandler($repository);
        $result = $handler->handle();

        $this->assertArrayHasKey('groups', $result);
        $this->assertCount(2, $result['groups']);

        $appGroup = collect($result['groups'])->firstWhere('name', 'app');
        $this->assertSame('基础信息', $appGroup['label']);
        $this->assertSame('内部下午茶', $appGroup['items'][0]['value']);

        $paymentGroup = collect($result['groups'])->firstWhere('name', 'payment');
        $this->assertSame('****', $paymentGroup['items'][0]['value']);
        $this->assertTrue($paymentGroup['items'][0]['is_sensitive']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=GetSystemConfigsHandlerTest`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Application\SystemConfig;

final class ConfigGroupLabels
{
    public const MAP = [
        'app' => '基础信息',
        'payment' => '支付配置',
        'storage' => '存储配置',
        'order' => '订单配置',
    ];
}
```

```php
<?php

namespace App\Application\SystemConfig\GetSystemConfigs;

use App\Application\SystemConfig\ConfigGroupLabels;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;

class GetSystemConfigsHandler
{
    public function __construct(
        private readonly SystemConfigRepositoryInterface $repository,
    ) {}

    public function handle(): array
    {
        $grouped = [];

        foreach ($this->repository->all() as $config) {
            $grouped[$config->group][] = [
                'key' => $config->key,
                'value' => $config->displayValue(),
                'is_sensitive' => $config->isSensitive,
                'description' => $config->description,
            ];
        }

        $groups = [];
        foreach ($grouped as $name => $items) {
            $groups[] = [
                'name' => $name,
                'label' => ConfigGroupLabels::MAP[$name] ?? $name,
                'items' => $items,
            ];
        }

        return ['groups' => $groups];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec backend php artisan test --filter=GetSystemConfigsHandlerTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Application/SystemConfig/ \
        backend/tests/Unit/Application/SystemConfig/
git commit -m "feat(M01): add GetSystemConfigsHandler"
```

---

### Task 7: UpdateSystemConfigsHandler

**Files:**
- Create: `backend/app/Application/SystemConfig/DTO/SystemConfigItemDto.php`
- Create: `backend/app/Application/SystemConfig/UpdateSystemConfigs/UpdateSystemConfigsHandler.php`
- Test: `backend/tests/Unit/Application/SystemConfig/UpdateSystemConfigsHandlerTest.php`

**Interfaces:**
- Consumes: `SystemConfigRepositoryInterface`, `SystemConfig::MASK_PLACEHOLDER`, `GetSystemConfigsHandler`
- Produces: `SystemConfigItemDto` — readonly `group`, `key`, `value`
- Produces: `UpdateSystemConfigsHandler::handle(SystemConfigItemDto[]): array` — returns GET shape

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Application\SystemConfig;

use App\Application\SystemConfig\DTO\SystemConfigItemDto;
use App\Application\SystemConfig\GetSystemConfigs\GetSystemConfigsHandler;
use App\Application\SystemConfig\UpdateSystemConfigs\UpdateSystemConfigsHandler;
use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateSystemConfigsHandlerTest extends TestCase
{
    #[Test]
    public function handle_updates_values_and_skips_mask_placeholder(): void
    {
        $repository = $this->createMock(SystemConfigRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('updateValue')
            ->with('app', 'name', '内部晚餐');
        $repository->expects($this->never())
            ->method('updateValue')
            ->with('payment', 'wechat.mch_id', '****');

        $getHandler = $this->createMock(GetSystemConfigsHandler::class);
        $getHandler->method('handle')->willReturn(['groups' => []]);

        $handler = new UpdateSystemConfigsHandler($repository, $getHandler);

        $handler->handle([
            new SystemConfigItemDto('app', 'name', '内部晚餐'),
            new SystemConfigItemDto('payment', 'wechat.mch_id', SystemConfig::MASK_PLACEHOLDER),
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec backend php artisan test --filter=UpdateSystemConfigsHandlerTest`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Application\SystemConfig\DTO;

readonly class SystemConfigItemDto
{
    public function __construct(
        public string $group,
        public string $key,
        public string $value,
    ) {}
}
```

```php
<?php

namespace App\Application\SystemConfig\UpdateSystemConfigs;

use App\Application\SystemConfig\DTO\SystemConfigItemDto;
use App\Application\SystemConfig\GetSystemConfigs\GetSystemConfigsHandler;
use App\Domain\SystemConfig\Entities\SystemConfig;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;

class UpdateSystemConfigsHandler
{
    public function __construct(
        private readonly SystemConfigRepositoryInterface $repository,
        private readonly GetSystemConfigsHandler $getHandler,
    ) {}

    /** @param SystemConfigItemDto[] $items */
    public function handle(array $items): array
    {
        foreach ($items as $item) {
            if ($item->value === SystemConfig::MASK_PLACEHOLDER) {
                continue;
            }

            $this->repository->updateValue($item->group, $item->key, $item->value);
        }

        return $this->getHandler->handle();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec backend php artisan test --filter=UpdateSystemConfigsHandlerTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Application/SystemConfig/DTO/ \
        backend/app/Application/SystemConfig/UpdateSystemConfigs/ \
        backend/tests/Unit/Application/SystemConfig/UpdateSystemConfigsHandlerTest.php
git commit -m "feat(M01): add UpdateSystemConfigsHandler"
```

---

### Task 8: Http 层 — Request、Controller、路由

**Files:**
- Create: `backend/app/Http/Requests/Admin/UpdateSystemConfigsRequest.php`
- Create: `backend/app/Http/Controllers/Admin/SystemConfigController.php`
- Modify: `backend/routes/api.php`

**Interfaces:**
- Consumes: `GetSystemConfigsHandler`, `UpdateSystemConfigsHandler`, `SystemConfigItemDto`, `ApiResponse`
- Produces: `GET /api/v1/admin/configs`, `PUT /api/v1/admin/configs` behind `auth:sanctum`

- [ ] **Step 1: Create Form Request**

```php
<?php

namespace App\Http\Requests\Admin;

use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSystemConfigsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'configs' => ['required', 'array', 'min:1'],
            'configs.*.group' => ['required', 'string', 'in:app,payment,storage,order'],
            'configs.*.key' => ['required', 'string', 'max:100'],
            'configs.*.value' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('configs', []) as $index => $config) {
                if (! is_array($config)) {
                    continue;
                }

                $exists = SystemConfigModel::query()
                    ->where('group', $config['group'] ?? '')
                    ->where('key', $config['key'] ?? '')
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add(
                        "configs.{$index}.key",
                        'Unknown config key for the given group.',
                    );
                }
            }
        });
    }
}
```

- [ ] **Step 2: Create Controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Application\SystemConfig\DTO\SystemConfigItemDto;
use App\Application\SystemConfig\GetSystemConfigs\GetSystemConfigsHandler;
use App\Application\SystemConfig\UpdateSystemConfigs\UpdateSystemConfigsHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemConfigsRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class SystemConfigController extends Controller
{
    public function index(GetSystemConfigsHandler $handler): JsonResponse
    {
        return ApiResponse::success($handler->handle());
    }

    public function update(
        UpdateSystemConfigsRequest $request,
        UpdateSystemConfigsHandler $handler,
    ): JsonResponse {
        $items = array_map(
            fn (array $config) => new SystemConfigItemDto(
                $config['group'],
                $config['key'],
                $config['value'],
            ),
            $request->validated('configs'),
        );

        return ApiResponse::success($handler->handle($items));
    }
}
```

- [ ] **Step 3: Register routes**

在 `backend/routes/api.php` 追加：

```php
<?php

use App\Http\Controllers\Admin\SystemConfigController;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ApiResponse::success(['status' => 'healthy']));

Route::middleware('auth:sanctum')->prefix('admin')->group(function (): void {
    Route::get('/configs', [SystemConfigController::class, 'index']);
    Route::put('/configs', [SystemConfigController::class, 'update']);
});
```

- [ ] **Step 4: Commit**

```bash
git add backend/app/Http/Requests/Admin/ \
        backend/app/Http/Controllers/Admin/ \
        backend/routes/api.php
git commit -m "feat(M01): add admin system config API endpoints"
```

---

### Task 9: DI 绑定

**Files:**
- Modify: `backend/app/Providers/AppServiceProvider.php`

**Interfaces:**
- Binds: `ConfigEncryptionInterface` → `LaravelConfigEncryption`
- Binds: `SystemConfigRepositoryInterface` → `EloquentSystemConfigRepository`

- [ ] **Step 1: Register bindings**

```php
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Domain\SystemConfig\Services\ConfigEncryptionInterface;
use App\Infrastructure\Encryption\LaravelConfigEncryption;
use App\Infrastructure\Persistence\Eloquent\EloquentSystemConfigRepository;

public function register(): void
{
    $this->app->bind(ConfigEncryptionInterface::class, LaravelConfigEncryption::class);
    $this->app->bind(SystemConfigRepositoryInterface::class, EloquentSystemConfigRepository::class);
}
```

- [ ] **Step 2: Commit**

```bash
git add backend/app/Providers/AppServiceProvider.php
git commit -m "feat(M01): bind config repository and encryption services"
```

---

### Task 10: Feature 测试

**Files:**
- Create: `backend/tests/Feature/Admin/SystemConfigApiTest.php`

**Interfaces:**
- Consumes: all M01 components end-to-end
- Covers: GET/PUT happy path, 401, 422 unknown key, DB ciphertext, mask skip on PUT

- [ ] **Step 1: Write Feature test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Infrastructure\Encryption\LaravelConfigEncryption;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use App\Models\User;
use Database\Seeders\SystemConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemConfigApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemConfigSeeder::class);
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    #[Test]
    public function unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/admin/configs')
            ->assertUnauthorized();
    }

    #[Test]
    public function get_configs_returns_grouped_response_with_masked_sensitive_values(): void
    {
        $encryption = new LaravelConfigEncryption;
        SystemConfigModel::where('group', 'payment')
            ->where('key', 'wechat.mch_id')
            ->update(['value' => $encryption->encrypt('1234567890')]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/admin/configs');

        $response->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.groups.0.name', 'app');

        $paymentGroup = collect($response->json('data.groups'))
            ->firstWhere('name', 'payment');

        $mchItem = collect($paymentGroup['items'])
            ->firstWhere('key', 'wechat.mch_id');

        $this->assertSame('****', $mchItem['value']);
    }

    #[Test]
    public function put_configs_updates_values_and_returns_refreshed_list(): void
    {
        $response = $this->withToken($this->token)
            ->putJson('/api/v1/admin/configs', [
                'configs' => [
                    ['group' => 'app', 'key' => 'name', 'value' => '内部晚餐'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', 0);

        $appGroup = collect($response->json('data.groups'))
            ->firstWhere('name', 'app');

        $nameItem = collect($appGroup['items'])->firstWhere('key', 'name');
        $this->assertSame('内部晚餐', $nameItem['value']);
    }

    #[Test]
    public function put_with_mask_placeholder_does_not_overwrite_sensitive_value(): void
    {
        $encryption = new LaravelConfigEncryption;
        $originalCipher = $encryption->encrypt('secret-mch-id');

        SystemConfigModel::where('group', 'payment')
            ->where('key', 'wechat.mch_id')
            ->update(['value' => $originalCipher]);

        $this->withToken($this->token)
            ->putJson('/api/v1/admin/configs', [
                'configs' => [
                    ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '****'],
                ],
            ])
            ->assertOk();

        $stored = SystemConfigModel::where('group', 'payment')
            ->where('key', 'wechat.mch_id')
            ->value('value');

        $this->assertSame($originalCipher, $stored);
    }

    #[Test]
    public function put_unknown_config_key_returns_422(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/v1/admin/configs', [
                'configs' => [
                    ['group' => 'app', 'key' => 'unknown_key', 'value' => 'x'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    #[Test]
    public function config_values_are_stored_encrypted_in_database(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/v1/admin/configs', [
                'configs' => [
                    ['group' => 'app', 'key' => 'name', 'value' => '明文测试'],
                ],
            ]);

        $raw = SystemConfigModel::where('group', 'app')
            ->where('key', 'name')
            ->value('value');

        $this->assertNotSame('明文测试', $raw);
    }
}
```

- [ ] **Step 2: Run Feature test**

Run: `docker compose exec backend php artisan test --filter=SystemConfigApiTest`
Expected: PASS (6 tests)

- [ ] **Step 3: Run full test suite**

Run: `docker compose exec backend php artisan test`
Expected: ALL PASS

- [ ] **Step 4: Commit**

```bash
git add backend/tests/Feature/Admin/
git commit -m "test(M01): add system config API feature tests"
```

---

### Task 11: 验收记录与 Spec 状态更新

**Files:**
- Create: `docs/superpowers/records/2026-07-12-M01-system-config.md`
- Modify: `docs/superpowers/specs/2026-07-12-internal-mall-design.md`（M01 状态 → ✅）

- [ ] **Step 1: Run final verification**

Run: `docker compose exec backend php artisan test`
Expected: all tests green

Run: `docker compose exec backend php artisan db:seed --class=SystemConfigSeeder`
Expected: 13 config rows

- [ ] **Step 2: Write execution record**

记录：实施日期、测试结果、验收清单勾选、已知限制（无角色检查，留 M03）。

- [ ] **Step 3: Update internal mall spec module table**

M01 行状态改为 ✅。

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/records/2026-07-12-M01-system-config.md \
        docs/superpowers/specs/2026-07-12-internal-mall-design.md
git commit -m "docs(M01): add execution record and mark module complete"
```

---

## Self-Review

| Spec 要求 | 对应 Task |
|---|---|
| Migration `system_configs` | Task 1 |
| `is_sensitive` 字段 | Task 1 |
| Domain 实体 + 仓储/加密接口 | Task 2 |
| Laravel Crypt 加解密 | Task 3 |
| 换 APP_KEY 无法解密 | Task 3 test |
| Eloquent 仓储 | Task 4 |
| Seeder 13 项默认配置 | Task 5 |
| GET 分组 + 敏感脱敏 | Task 6 |
| PUT 批量更新 + **** 跳过 | Task 7 |
| `auth:sanctum` 鉴权 | Task 8 |
| 未知 key → 422 | Task 8 Request + Task 10 test |
| DI 绑定 | Task 9 |
| Feature 测试全链路 | Task 10 |
| `php artisan test` 通过 | Task 10–11 |
| 验收记录 | Task 11 |

**Placeholder scan:** 无 TBD / TODO / "implement later"。

**Type consistency:** `SystemConfigItemDto`, `GetSystemConfigsHandler::handle(): array`, `UpdateSystemConfigsHandler::handle(array): array` 全计划一致。
