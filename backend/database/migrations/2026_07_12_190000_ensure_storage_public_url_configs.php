<?php

use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $items = [
            [
                'group' => 'storage',
                'key' => 'oss.public_base_url',
                'value' => '',
                'is_sensitive' => false,
                'description' => '图片公开访问域名',
            ],
            [
                'group' => 'storage',
                'key' => 'local.public_base_url',
                'value' => '',
                'is_sensitive' => false,
                'description' => '图片公开访问域名',
            ],
        ];

        foreach ($items as $item) {
            SystemConfigModel::query()->updateOrCreate(
                ['group' => $item['group'], 'key' => $item['key']],
                [
                    'value' => $item['value'],
                    'is_sensitive' => $item['is_sensitive'],
                    'description' => $item['description'],
                ],
            );
        }
    }

    public function down(): void
    {
        SystemConfigModel::query()
            ->where('group', 'storage')
            ->whereIn('key', ['oss.public_base_url', 'local.public_base_url'])
            ->delete();
    }
};
