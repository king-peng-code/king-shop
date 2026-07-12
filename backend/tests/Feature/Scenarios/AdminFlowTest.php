<?php

namespace Tests\Feature\Scenarios;

use App\Infrastructure\Persistence\Eloquent\Models\UploadModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLocalStoragePublicBaseUrl();
    }

    #[Test]
    public function admin_complete_management_flow(): void
    {
        // 1. 管理员登录
        $admin = UserModel::factory()->admin()->create([
            'password' => Hash::make('admin123'),
            'must_change_password' => false,
        ]);
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone' => $admin->phone,
            'password' => 'admin123',
        ])->assertOk();
        $token = $loginResponse->json('data.token');

        // 2. 添加分类
        $categoryResponse = $this->withToken($token)
            ->postJson('/api/v1/admin/categories', ['name' => '饮品', 'sort' => 1])
            ->assertCreated()
            ->assertJsonPath('data.name', '饮品')
            ->assertJsonPath('data.status', 'active');
        $categoryId = $categoryResponse->json('data.id');

        // 3. 添加上传记录 + 商品
        $upload = UploadModel::factory()->create();
        $this->withToken($token)
            ->postJson('/api/v1/admin/products', [
                'category_id' => $categoryId,
                'name' => '拿铁',
                'price' => 1500,
                'upload_id' => $upload->id,
                'status' => 'on_sale',
            ])->assertCreated()
            ->assertJsonPath('data.name', '拿铁')
            ->assertJsonPath('data.price', 1500)
            ->assertJsonPath('data.image_path', $upload->path);

        // 4. 添加员工
        $employeeResponse = $this->withToken($token)
            ->postJson('/api/v1/admin/employees', [
                'name' => '张三',
                'phone' => '13890000099',
            ])->assertCreated()
            ->assertJsonPath('data.phone', '13890000099')
            ->assertJsonPath('data.must_change_password', true);
        $employeeId = $employeeResponse->json('data.id');

        // 5. 重置员工密码
        $this->withToken($token)
            ->putJson("/api/v1/admin/employees/{$employeeId}", [
                'name' => '张三',
                'role' => 'employee',
                'status' => 'active',
                'reset_password' => true,
            ])->assertOk()
            ->assertJsonPath('data.must_change_password', true);

        // 6. 获取配置列表
        $this->withToken($token)
            ->getJson('/api/v1/admin/configs')
            ->assertOk()
            ->assertJsonPath('code', 0);

        // 7. 修改非敏感配置
        $this->withToken($token)
            ->putJson('/api/v1/admin/configs', [
                'configs' => [['group' => 'app', 'key' => 'name', 'value' => '测试店铺']],
            ])->assertOk();

        // 8. 验证配置已更新
        $configResponse = $this->withToken($token)
            ->getJson('/api/v1/admin/configs')
            ->assertOk();
        $appGroup = collect($configResponse->json('data.groups'))
            ->firstWhere('name', 'app');
        $nameItem = collect($appGroup['items'])->firstWhere('key', 'name');
        $this->assertSame('测试店铺', $nameItem['value']);

        // 9. 查看仪表盘统计
        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('code', 0);
    }
}
