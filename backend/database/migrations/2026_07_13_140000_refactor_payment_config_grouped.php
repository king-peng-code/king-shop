<?php

use App\Infrastructure\Encryption\LaravelConfigEncryption;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $encryption = new LaravelConfigEncryption;

        // 添加支付宝付款分组配置
        $records = [
            [
                'group' => 'payment',
                'key' => 'alipay.enabled',
                'value' => '0',
                'is_sensitive' => false,
                'description' => '支付宝付款 - 启用',
            ],
            [
                'group' => 'payment',
                'key' => 'alipay.mode',
                'value' => 'sandbox',
                'is_sensitive' => false,
                'description' => '支付宝付款 - 模式',
            ],
            [
                'group' => 'payment',
                'key' => 'wechat.enabled',
                'value' => '0',
                'is_sensitive' => false,
                'description' => '微信付款 - 启用',
            ],
            [
                'group' => 'payment',
                'key' => 'wechat.mode',
                'value' => 'sandbox',
                'is_sensitive' => false,
                'description' => '微信付款 - 模式',
            ],
        ];

        foreach ($records as $record) {
            SystemConfigModel::updateOrCreate(
                ['group' => $record['group'], 'key' => $record['key']],
                [
                    'value' => $record['is_sensitive']
                        ? $encryption->encrypt($record['value'])
                        : $record['value'],
                    'is_sensitive' => $record['is_sensitive'],
                    'description' => $record['description'],
                ],
            );
        }

        // 清理旧的互斥 provider 配置（不再使用单项渠道选择）
        SystemConfigModel::query()
            ->where('group', 'payment')
            ->where('key', 'provider')
            ->delete();
    }

    public function down(): void
    {
        // 还原旧的 provider 配置
        SystemConfigModel::updateOrCreate(
            ['group' => 'payment', 'key' => 'provider'],
            [
                'value' => 'alipay_sandbox',
                'is_sensitive' => false,
                'description' => '支付渠道',
            ],
        );

        // 删除新加的记录
        SystemConfigModel::query()
            ->where('group', 'payment')
            ->whereIn('key', ['alipay.enabled', 'alipay.mode', 'wechat.enabled', 'wechat.mode'])
            ->delete();
    }
};
