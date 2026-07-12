<?php

namespace Tests\Feature\Auth;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnsureAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employee_token_cannot_access_admin_routes(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/employees')
            ->assertForbidden()
            ->assertJsonPath('message', '无权访问');
    }

    #[Test]
    public function admin_token_can_access_admin_routes(): void
    {
        $user = UserModel::factory()->admin()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/employees')
            ->assertOk();
    }
}
