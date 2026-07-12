<?php

namespace Tests\Feature\Auth;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordChangeMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function must_change_password_blocks_admin_routes(): void
    {
        $user = UserModel::factory()->mustChangePassword()->admin()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/employees')
            ->assertForbidden()
            ->assertJsonPath('code', 40301);
    }

    #[Test]
    public function must_change_password_allows_password_change_route(): void
    {
        $user = UserModel::factory()->mustChangePassword()->create([
            'password' => Hash::make('123456'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/auth/password', [
                'current_password' => '123456',
                'new_password' => 'newpass1',
                'new_password_confirmation' => 'newpass1',
            ])
            ->assertOk();
    }
}
