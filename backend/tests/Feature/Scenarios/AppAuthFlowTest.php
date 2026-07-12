<?php

namespace Tests\Feature\Scenarios;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employee_auth_flow(): void
    {
        $phone = '13800001001';
        $oldPassword = 'old_password';
        $newPassword = 'new_password';

        // 1. 创建需改密员工
        UserModel::factory()->mustChangePassword()->create([
            'phone' => $phone,
            'password' => Hash::make($oldPassword),
        ]);

        // 2. 登录 → 拿到 token + must_change_password=true
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone' => $phone,
            'password' => $oldPassword,
        ])->assertOk()
            ->assertJsonPath('data.must_change_password', true);
        $token = $loginResponse->json('data.token');

        // 3. 修改密码
        $this->withToken($token)
            ->putJson('/api/v1/auth/password', [
                'current_password' => $oldPassword,
                'new_password' => $newPassword,
                'new_password_confirmation' => $newPassword,
            ])->assertOk();

        // 4. 查看个人信息
        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.phone', $phone);

        // 5. 退出登录
        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // 6. 旧密码登录失败
        $this->postJson('/api/v1/auth/login', [
            'phone' => $phone,
            'password' => $oldPassword,
        ])->assertUnauthorized();

        // 7. 新密码重新登录 → must_change_password=false
        $this->postJson('/api/v1/auth/login', [
            'phone' => $phone,
            'password' => $newPassword,
        ])->assertOk()
            ->assertJsonPath('data.must_change_password', false);
    }
}
