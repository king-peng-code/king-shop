<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\ExternalUser\Entities\ExternalUser;
use App\Domain\ExternalUser\Repositories\ExternalUserRepositoryInterface;
use App\Domain\ExternalUser\ValueObjects\ExternalUserProvider;
use App\Infrastructure\Persistence\Eloquent\Models\ExternalUserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentExternalUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function find_by_provider_and_external_id_returns_domain_user(): void
    {
        ExternalUserModel::factory()->create([
            'provider' => 'wechat',
            'external_id' => 'wx-openid-001',
            'name' => '张三',
        ]);

        $user = app(ExternalUserRepositoryInterface::class)
            ->findByProviderAndExternalId('wechat', 'wx-openid-001');

        $this->assertNotNull($user);
        $this->assertSame('wechat', $user->provider->value);
        $this->assertSame('wx-openid-001', $user->externalId);
        $this->assertSame('张三', $user->name);
    }

    #[Test]
    public function save_persists_new_external_user(): void
    {
        $now = new \DateTimeImmutable;
        $user = new ExternalUser(
            id: null,
            provider: ExternalUserProvider::fromString('fake'),
            externalId: 'fake-001',
            name: '测试用户',
            phone: '13800000001',
            createdAt: $now,
            updatedAt: $now,
        );

        $saved = app(ExternalUserRepositoryInterface::class)->save($user);

        $this->assertNotNull($saved->id);
        $this->assertSame('fake', $saved->provider->value);
        $this->assertSame('fake-001', $saved->externalId);
        $this->assertDatabaseHas('external_users', [
            'provider' => 'fake',
            'external_id' => 'fake-001',
            'name' => '测试用户',
            'phone' => '13800000001',
        ]);
    }
}
