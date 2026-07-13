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
    public function all_returns_plaintext_for_non_sensitive_configs(): void
    {
        SystemConfigModel::create([
            'group' => 'app',
            'key' => 'name',
            'value' => '内部下午茶',
            'is_sensitive' => false,
            'description' => '商城名称',
        ]);

        $configs = $this->repository->all();

        // 1 from test + 9 from migrations (share_title, share_message, share_copy_text, tag_presets, alipay.enabled, alipay.mode, wechat.enabled, wechat.mode, fake.enabled)
        $this->assertCount(10, $configs);
        $this->assertInstanceOf(SystemConfig::class, $configs[0]);
        $this->assertSame('内部下午茶', $configs[0]->value);
    }

    #[Test]
    public function non_sensitive_value_is_stored_as_plaintext_in_database(): void
    {
        SystemConfigModel::create([
            'group' => 'app',
            'key' => 'name',
            'value' => 'placeholder',
            'is_sensitive' => false,
        ]);

        $this->repository->updateValue('app', 'name', '内部下午茶');

        $raw = SystemConfigModel::where('group', 'app')->where('key', 'name')->value('value');

        $this->assertSame('内部下午茶', $raw);
    }

    #[Test]
    public function sensitive_value_is_stored_encrypted_in_database(): void
    {
        SystemConfigModel::create([
            'group' => 'payment',
            'key' => 'wechat.mch_id',
            'value' => (new LaravelConfigEncryption)->encrypt('placeholder'),
            'is_sensitive' => true,
        ]);

        $this->repository->updateValue('payment', 'wechat.mch_id', '1234567890');

        $raw = SystemConfigModel::where('group', 'payment')
            ->where('key', 'wechat.mch_id')
            ->value('value');

        $this->assertNotSame('1234567890', $raw);
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
            'value' => 'x',
            'is_sensitive' => false,
        ]);

        $this->assertTrue($this->repository->exists('app', 'name'));
        $this->assertFalse($this->repository->exists('app', 'missing'));
    }
}
