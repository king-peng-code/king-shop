<?php

namespace Database\Seeders;

use App\Infrastructure\Encryption\LaravelConfigEncryption;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use Illuminate\Database\Seeder;

class SystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        $encryption = new LaravelConfigEncryption;

        $configs = [
            ['group' => 'app', 'key' => 'name', 'value' => '内部下午茶', 'is_sensitive' => false, 'description' => '商城名称'],
            ['group' => 'order', 'key' => 'auto_cancel_minutes', 'value' => '30', 'is_sensitive' => false, 'description' => '未支付自动取消（分钟）'],
            // 支付宝付款
            ['group' => 'payment', 'key' => 'alipay.enabled', 'value' => '0', 'is_sensitive' => false, 'description' => '支付宝付款 - 启用'],
            ['group' => 'payment', 'key' => 'alipay.mode', 'value' => 'sandbox', 'is_sensitive' => false, 'description' => '支付宝付款 - 模式'],
            ['group' => 'payment', 'key' => 'alipay.app_id', 'value' => '', 'is_sensitive' => false, 'description' => '支付宝付款 - App ID'],
            ['group' => 'payment', 'key' => 'alipay.private_key', 'value' => '', 'is_sensitive' => true, 'description' => '支付宝付款 - 应用私钥'],
            ['group' => 'payment', 'key' => 'alipay.public_key', 'value' => '', 'is_sensitive' => true, 'description' => '支付宝付款 - 支付宝公钥'],
            // 微信付款
            ['group' => 'payment', 'key' => 'wechat.enabled', 'value' => '0', 'is_sensitive' => false, 'description' => '微信付款 - 启用'],
            ['group' => 'payment', 'key' => 'wechat.mode', 'value' => 'sandbox', 'is_sensitive' => false, 'description' => '微信付款 - 模式'],
            ['group' => 'payment', 'key' => 'wechat.app_id', 'value' => '', 'is_sensitive' => false, 'description' => '微信付款 - App ID'],
            ['group' => 'payment', 'key' => 'wechat.mch_id', 'value' => '', 'is_sensitive' => true, 'description' => '微信付款 - 商户号'],
            ['group' => 'payment', 'key' => 'wechat.api_key', 'value' => '', 'is_sensitive' => true, 'description' => '微信付款 - API 密钥'],
            ['group' => 'payment', 'key' => 'wechat.cert', 'value' => '', 'is_sensitive' => true, 'description' => '微信付款 - 商户证书'],
            ['group' => 'storage', 'key' => 'driver', 'value' => 'local', 'is_sensitive' => false, 'description' => '存储驱动'],
            ['group' => 'storage', 'key' => 'local.public_base_url', 'value' => '', 'is_sensitive' => false, 'description' => '图片公开访问域名'],
            ['group' => 'storage', 'key' => 'oss.bucket', 'value' => '', 'is_sensitive' => true, 'description' => 'OSS Bucket'],
            ['group' => 'storage', 'key' => 'oss.endpoint', 'value' => '', 'is_sensitive' => true, 'description' => 'OSS Endpoint（API 地址）'],
            ['group' => 'storage', 'key' => 'oss.public_base_url', 'value' => '', 'is_sensitive' => false, 'description' => '图片公开访问域名'],
            ['group' => 'storage', 'key' => 'oss.access_key', 'value' => '', 'is_sensitive' => true, 'description' => 'OSS Access Key'],
            ['group' => 'storage', 'key' => 'oss.secret_key', 'value' => '', 'is_sensitive' => true, 'description' => 'OSS Secret Key'],
            ['group' => 'external_user', 'key' => 'tag_presets', 'value' => 'VIP,企业,个人,已失效', 'is_sensitive' => false, 'description' => '代付人预设标签（英文逗号分隔）'],
            ['group' => 'order', 'key' => 'share_title', 'value' => '帮我付一下', 'is_sensitive' => false, 'description' => '代付分享标题'],
            ['group' => 'order', 'key' => 'share_message', 'value' => "Hi，来{brand_name}就差{amount}元～\n\n{share_url}", 'is_sensitive' => false, 'description' => '代付分享内容模板。可用占位符：{brand_name} {amount} {order_no} {expires_at} {share_url}'],
            ['group' => 'order', 'key' => 'share_copy_text', 'value' => '{share_url}', 'is_sensitive' => false, 'description' => '复制链接文案模板。可用占位符：{brand_name} {order_no} {amount} {expires_at} {share_url}'],
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
