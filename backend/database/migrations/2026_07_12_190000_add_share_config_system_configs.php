<?php

use App\Infrastructure\Encryption\LaravelConfigEncryption;
use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $encryption = new LaravelConfigEncryption;

        $records = [
            [
                'group' => 'order',
                'key' => 'share_title',
                'value' => '帮我付一下',
                'is_sensitive' => false,
                'description' => '代付分享标题',
            ],
            [
                'group' => 'order',
                'key' => 'share_message',
                'value' => implode("\n", [
                    '请帮我支付订单 {order_no}',
                    '金额 ¥{amount}',
                    '请在 {expires_at} 前完成支付',
                    '',
                    '{share_url}',
                ]),
                'is_sensitive' => false,
                'description' => '代付分享内容模板。可用占位符：{brand_name} {amount} {order_no} {expires_at} {share_url}',
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
    }

    public function down(): void
    {
        SystemConfigModel::query()
            ->where('group', 'order')
            ->whereIn('key', ['share_title', 'share_message'])
            ->delete();
    }
};
