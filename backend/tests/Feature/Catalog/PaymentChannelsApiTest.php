<?php

namespace Tests\Feature\Catalog;

use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentChannelsApiTest extends TestCase
{
    use RefreshDatabase;

    private const ALIPAY_KEYS = ['alipay.app_id', 'alipay.private_key', 'alipay.public_key'];
    private const WECHAT_KEYS = ['wechat.app_id', 'wechat.mch_id', 'wechat.api_key'];

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure config records exist for all payment keys (migrations only cover enabled/mode)
        foreach (self::ALIPAY_KEYS as $key) {
            SystemConfigModel::query()->updateOrCreate(
                ['group' => 'payment', 'key' => $key],
                ['value' => '', 'is_sensitive' => str_contains($key, 'app_id') ? false : true, 'description' => 'test'],
            );
        }
        foreach (self::WECHAT_KEYS as $key) {
            SystemConfigModel::query()->updateOrCreate(
                ['group' => 'payment', 'key' => $key],
                ['value' => '', 'is_sensitive' => ! str_contains($key, 'app_id'), 'description' => 'test'],
            );
        }

        // Ensure fake.enabled exists (defaults to 0)
        SystemConfigModel::query()->updateOrCreate(
            ['group' => 'payment', 'key' => 'fake.enabled'],
            ['value' => '0', 'is_sensitive' => false, 'description' => 'test'],
        );
    }

    private function employeeToken(): string
    {
        $user = UserModel::factory()->create([
            'role' => 'employee',
            'must_change_password' => false,
        ]);

        return $user->createToken('test')->plainTextToken;
    }

    private function enableAlipay(): void
    {
        $encryption = new \App\Infrastructure\Encryption\LaravelConfigEncryption;
        SystemConfigModel::where('group', 'payment')->where('key', 'alipay.enabled')
            ->update(['value' => '1']);
        SystemConfigModel::where('group', 'payment')->where('key', 'alipay.app_id')
            ->update(['value' => '2021000000000000']);
        SystemConfigModel::where('group', 'payment')->where('key', 'alipay.private_key')
            ->update(['value' => $encryption->encrypt('test_private_key')]);
        SystemConfigModel::where('group', 'payment')->where('key', 'alipay.public_key')
            ->update(['value' => $encryption->encrypt('test_public_key')]);
    }

    private function enableWechat(): void
    {
        $encryption = new \App\Infrastructure\Encryption\LaravelConfigEncryption;
        SystemConfigModel::where('group', 'payment')->where('key', 'wechat.enabled')
            ->update(['value' => '1']);
        SystemConfigModel::where('group', 'payment')->where('key', 'wechat.app_id')
            ->update(['value' => 'wx123456']);
        SystemConfigModel::where('group', 'payment')->where('key', 'wechat.mch_id')
            ->update(['value' => $encryption->encrypt('1600000000')]);
        SystemConfigModel::where('group', 'payment')->where('key', 'wechat.api_key')
            ->update(['value' => $encryption->encrypt('abc123')]);
    }

    #[Test]
    public function unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/payment-channels')
            ->assertStatus(401);
    }

    #[Test]
    public function returns_only_fake_when_no_real_channels_enabled(): void
    {
        $response = $this->withToken($this->employeeToken())
            ->getJson('/api/v1/payment-channels')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'self_pay',
                    'proxy_pay',
                ],
            ]);

        // fake is always available in test environment
        $this->assertCount(1, $response->json('data.self_pay'));
        $this->assertSame('fake', $response->json('data.self_pay.0.value'));
        $this->assertCount(1, $response->json('data.proxy_pay'));
        $this->assertSame('fake', $response->json('data.proxy_pay.0.value'));
    }

    #[Test]
    public function returns_only_alipay_when_only_alipay_enabled(): void
    {
        $this->enableAlipay();

        $response = $this->withToken($this->employeeToken())
            ->getJson('/api/v1/payment-channels');

        $selfPay = $response->json('data.self_pay');
        $this->assertCount(2, $selfPay); // alipay_sandbox + fake (test env)
        $this->assertSame('alipay_sandbox', $selfPay[0]['value']);
        $this->assertSame('支付宝', $selfPay[0]['label']);
    }

    #[Test]
    public function returns_only_wechat_when_only_wechat_enabled(): void
    {
        $this->enableWechat();

        $response = $this->withToken($this->employeeToken())
            ->getJson('/api/v1/payment-channels')
            ->assertOk();

        $selfPay = $response->json('data.self_pay');
        $this->assertCount(2, $selfPay); // wechat + fake (test env)
        $this->assertSame('wechat', $selfPay[0]['value']);
        $this->assertSame('微信支付', $selfPay[0]['label']);
    }

    #[Test]
    public function returns_both_when_both_enabled(): void
    {
        $this->enableAlipay();
        $this->enableWechat();

        $response = $this->withToken($this->employeeToken())
            ->getJson('/api/v1/payment-channels')
            ->assertOk();

        $selfPay = $response->json('data.self_pay');
        $this->assertCount(3, $selfPay); // alipay_sandbox + wechat + fake

        $values = array_column($selfPay, 'value');
        $this->assertContains('alipay_sandbox', $values);
        $this->assertContains('wechat', $values);
        $this->assertContains('fake', $values);
    }

    #[Test]
    public function returns_proxy_pay_channels(): void
    {
        $this->enableWechat();

        $response = $this->withToken($this->employeeToken())
            ->getJson('/api/v1/payment-channels')
            ->assertOk();

        $proxyPay = $response->json('data.proxy_pay');
        // wechat is available for proxy pay + fake in test env
        $this->assertCount(2, $proxyPay);
        $this->assertSame('wechat', $proxyPay[0]['value']);
    }

    #[Test]
    public function proxy_pay_excludes_alipay(): void
    {
        $this->enableAlipay();

        $response = $this->withToken($this->employeeToken())
            ->getJson('/api/v1/payment-channels')
            ->assertOk();

        $proxyPay = $response->json('data.proxy_pay');
        $values = array_column($proxyPay, 'value');
        $this->assertNotContains('alipay_sandbox', $values);
    }

    #[Test]
    public function fake_channel_can_be_enabled_by_config(): void
    {
        // Enable fake via config
        SystemConfigModel::where('group', 'payment')->where('key', 'fake.enabled')
            ->update(['value' => '1']);

        $response = $this->withToken($this->employeeToken())
            ->getJson('/api/v1/payment-channels')
            ->assertOk();

        $selfPay = $response->json('data.self_pay');
        $selfValues = array_column($selfPay, 'value');
        $this->assertContains('fake', $selfValues);

        $proxyPay = $response->json('data.proxy_pay');
        $proxyValues = array_column($proxyPay, 'value');
        $this->assertContains('fake', $proxyValues);
    }

    #[Test]
    public function returns_wechat_app_id_in_response(): void
    {
        $response = $this->withToken($this->employeeToken())
            ->getJson('/api/v1/payment-channels')
            ->assertOk();

        // When wechat.app_id is empty, wechat_app_id should be null
        $this->assertNull($response->json('data.wechat_app_id'));
    }

    #[Test]
    public function returns_wechat_app_id_when_configured(): void
    {
        SystemConfigModel::where('group', 'payment')->where('key', 'wechat.app_id')
            ->update(['value' => 'wx_test_app_id_123']);

        $response = $this->withToken($this->employeeToken())
            ->getJson('/api/v1/payment-channels')
            ->assertOk();

        $this->assertSame('wx_test_app_id_123', $response->json('data.wechat_app_id'));
    }
}
