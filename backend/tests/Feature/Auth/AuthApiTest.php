<?php

namespace Tests\Feature\Auth;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_returns_token_and_must_change_password_flag(): void
    {
        UserModel::factory()->mustChangePassword()->create([
            'phone' => '13800000001',
            'password' => Hash::make('123456'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'phone' => '13800000001',
            'password' => '123456',
        ])->assertOk()
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonPath('code', 0);
    }

    #[Test]
    public function disabled_user_cannot_login(): void
    {
        UserModel::factory()->disabled()->create([
            'phone' => '13800000001',
            'password' => Hash::make('123456'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'phone' => '13800000001',
            'password' => '123456',
        ])->assertForbidden()
            ->assertJsonPath('message', '账号已禁用');
    }

    #[Test]
    public function invalid_credentials_return_401(): void
    {
        UserModel::factory()->create([
            'phone' => '13800000001',
            'password' => Hash::make('123456'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'phone' => '13800000001',
            'password' => 'wrong',
        ])->assertUnauthorized();
    }

    #[Test]
    public function me_returns_current_user(): void
    {
        $user = UserModel::factory()->admin()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.phone', $user->phone);
    }

    #[Test]
    public function change_password_clears_must_change_password_flag(): void
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

        $this->assertFalse($user->fresh()->must_change_password);
    }

    #[Test]
    public function logout_revokes_token(): void
    {
        $user = UserModel::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $tokenId = $user->tokens()->first()->id;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    #[Test]
    public function super_admin_seeder_can_login(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $this->postJson('/api/v1/auth/login', [
            'phone' => '13800000000',
            'password' => 'admin123',
        ])->assertOk();
    }
}
