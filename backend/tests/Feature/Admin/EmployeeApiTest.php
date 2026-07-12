<?php

namespace Tests\Feature\Admin;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(string $role = 'admin'): string
    {
        $user = match ($role) {
            'super_admin' => UserModel::factory()->superAdmin()->create(),
            default => UserModel::factory()->admin()->create(),
        };

        return $user->createToken('test')->plainTextToken;
    }

    #[Test]
    public function admin_can_create_employee(): void
    {
        $response = $this->withToken($this->adminToken())
            ->postJson('/api/v1/admin/employees', [
                'name' => '张三',
                'phone' => '13890000005',
                'employee_no' => 'E005',
                'department' => '技术部',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.phone', '13890000005')
            ->assertJsonPath('data.must_change_password', true);

        $model = UserModel::query()->where('phone', '13890000005')->first();
        $this->assertTrue(Hash::check('123456', $model->password));
    }

    #[Test]
    public function employee_token_cannot_access_employees(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/employees')
            ->assertForbidden();
    }

    #[Test]
    public function keyword_search_finds_by_name(): void
    {
        UserModel::factory()->create(['name' => '张三', 'phone' => '13890000010']);
        UserModel::factory()->create(['name' => '李四', 'phone' => '13890000011']);

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/employees?keyword=张')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    #[Test]
    public function delete_employee_soft_disables(): void
    {
        $employee = UserModel::factory()->create();

        $this->withToken($this->adminToken())
            ->deleteJson("/api/v1/admin/employees/{$employee->id}")
            ->assertOk();

        $this->assertSame('disabled', $employee->fresh()->status);
    }

    #[Test]
    public function admin_cannot_assign_admin_role(): void
    {
        $this->withToken($this->adminToken())
            ->postJson('/api/v1/admin/employees', [
                'name' => '管理员',
                'phone' => '13890000006',
                'role' => 'admin',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function super_admin_can_assign_admin_role(): void
    {
        $this->withToken($this->adminToken('super_admin'))
            ->postJson('/api/v1/admin/employees', [
                'name' => '管理员',
                'phone' => '13890000007',
                'role' => 'admin',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'admin');
    }

    #[Test]
    public function reset_password_sets_default_and_must_change_flag(): void
    {
        $employee = UserModel::factory()->create([
            'must_change_password' => false,
            'password' => Hash::make('custom'),
        ]);

        $this->withToken($this->adminToken())
            ->putJson("/api/v1/admin/employees/{$employee->id}", [
                'name' => $employee->name,
                'role' => 'employee',
                'status' => 'active',
                'reset_password' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true);

        $employee->refresh();
        $this->assertTrue(Hash::check('123456', $employee->password));
    }

    #[Test]
    public function admin_cannot_disable_self(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->putJson("/api/v1/admin/employees/{$admin->id}", [
                'name' => $admin->name,
                'role' => 'admin',
                'status' => 'disabled',
            ])
            ->assertForbidden();
    }
}
