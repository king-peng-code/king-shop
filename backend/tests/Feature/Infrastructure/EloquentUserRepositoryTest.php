<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function find_by_phone_returns_domain_user(): void
    {
        UserModel::factory()->create(['phone' => '13800000001']);

        $user = app(UserRepositoryInterface::class)->findByPhone('13800000001');

        $this->assertNotNull($user);
        $this->assertSame('13800000001', $user->phone);
    }

    #[Test]
    public function search_by_keyword_matches_name(): void
    {
        UserModel::factory()->create(['name' => '张三', 'phone' => '13800000001']);
        UserModel::factory()->create(['name' => '李四', 'phone' => '13800000002']);

        $result = app(UserRepositoryInterface::class)->search('张', 1, 20);

        $this->assertSame(1, $result['total']);
        $this->assertSame('张三', $result['items'][0]->name);
    }
}
