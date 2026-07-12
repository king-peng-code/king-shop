<?php

namespace Database\Seeders;

use App\Infrastructure\Encryption\LaravelConfigEncryption;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use Illuminate\Database\Seeder;

class SystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        if (SystemConfigModel::query()->exists()) {
            $this->command?->info('System configs already exist, skipping seed.');

            return;
        }

        $encryption = new LaravelConfigEncryption;

        $configs = [
            ['group' => 'app', 'key' => 'name', 'value' => '内部下午茶', 'is_sensitive' => false, 'description' => '商城名称'],
            ['group' => 'order', 'key' => 'auto_cancel_minutes', 'value' => '30', 'is_sensitive' => false, 'description' => '未支付自动取消（分钟）'],
            ['group' => 'payment', 'key' => 'provider', 'value' => 'alipay_sandbox', 'is_sensitive' => false, 'description' => '支付渠道'],
            ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '', 'is_sensitive' => true, 'description' => '微信商户号'],
            ['group' => 'payment', 'key' => 'wechat.api_key', 'value' => '', 'is_sensitive' => true, 'description' => '微信 API 密钥'],
            ['group' => 'payment', 'key' => 'wechat.cert', 'value' => '', 'is_sensitive' => true, 'description' => '微信商户证书'],
            ['group' => 'payment', 'key' => 'alipay.app_id', 'value' => '', 'is_sensitive' => true, 'description' => '支付宝 App ID'],
            ['group' => 'payment', 'key' => 'alipay.private_key', 'value' => '', 'is_sensitive' => true, 'description' => '支付宝私钥'],
            ['group' => 'storage', 'key' => 'driver', 'value' => 'local', 'is_sensitive' => false, 'description' => '存储驱动'],
            ['group' => 'storage', 'key' => 'oss.bucket', 'value' => '', 'is_sensitive' => true, 'description' => 'OSS Bucket'],
            ['group' => 'storage', 'key' => 'oss.endpoint', 'value' => '', 'is_sensitive' => true, 'description' => 'OSS Endpoint'],
            ['group' => 'storage', 'key' => 'oss.access_key', 'value' => '', 'is_sensitive' => true, 'description' => 'OSS Access Key'],
            ['group' => 'storage', 'key' => 'oss.secret_key', 'value' => '', 'is_sensitive' => true, 'description' => 'OSS Secret Key'],
        ];

        foreach ($configs as $config) {
            SystemConfigModel::updateOrCreate(
                ['group' => $config['group'], 'key' => $config['key']],
                [
                    'value' => $config['is_sensitive']
                        ? $encryption->encrypt($config['value'])
                        : $config['value'],
                    'is_sensitive' => $config['is_sensitive'],
                    'description' => $config['description'],
                ],
            );
        }
    }
}
