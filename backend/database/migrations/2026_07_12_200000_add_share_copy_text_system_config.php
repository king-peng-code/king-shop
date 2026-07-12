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
                'key' => 'share_copy_text',
                'value' => '{share_url}',
                'is_sensitive' => false,
                'description' => '代付复制链接文案模板。可用占位符：{brand_name} {order_no} {amount} {expires_at} {share_url}',
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
            ->where('key', 'share_copy_text')
            ->delete();
    }
};
