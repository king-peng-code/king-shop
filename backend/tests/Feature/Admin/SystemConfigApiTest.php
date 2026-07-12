<?php

namespace Tests\Feature\Admin;

use App\Infrastructure\Encryption\LaravelConfigEncryption;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Database\Seeders\SystemConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemConfigApiTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemConfigSeeder::class);
        $this->user = UserModel::factory()->admin()->create();
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
    public function non_sensitive_config_values_are_stored_as_plaintext_in_database(): void
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

        $this->assertSame('明文测试', $raw);
    }

    #[Test]
    public function sensitive_config_values_are_stored_encrypted_in_database(): void
    {
        $superAdmin = UserModel::factory()->superAdmin()->create();
        $token = $superAdmin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/admin/configs', [
                'configs' => [
                    ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '1234567890'],
                ],
            ]);

        $raw = SystemConfigModel::where('group', 'payment')
            ->where('key', 'wechat.mch_id')
            ->value('value');

        $this->assertNotSame('1234567890', $raw);
    }

    #[Test]
    public function employee_token_cannot_access_configs(): void
    {
        $user = UserModel::factory()->create(['role' => 'employee']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/configs')
            ->assertForbidden();
    }

    #[Test]
    public function admin_cannot_update_sensitive_config_via_api(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/admin/configs', [
                'configs' => [
                    ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '1234567890'],
                ],
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 403)
            ->assertJsonPath('message', '无权修改敏感配置');
    }

    #[Test]
    public function super_admin_can_update_sensitive_config_via_api(): void
    {
        $superAdmin = UserModel::factory()->superAdmin()->create();
        $token = $superAdmin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/admin/configs', [
                'configs' => [
                    ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '9876543210'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('code', 0);
    }

    #[Test]
    public function admin_can_update_non_sensitive_config_via_api(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/admin/configs', [
                'configs' => [
                    ['group' => 'app', 'key' => 'name', 'value' => '管理员可改'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('code', 0);

        $appGroup = collect($this->withToken($token)->getJson('/api/v1/admin/configs')->json('data.groups'))
            ->firstWhere('name', 'app');
        $nameItem = collect($appGroup['items'])->firstWhere('key', 'name');
        $this->assertSame('管理员可改', $nameItem['value']);
    }
}
