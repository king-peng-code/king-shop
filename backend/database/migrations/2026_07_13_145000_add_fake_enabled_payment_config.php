<?php

use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SystemConfigModel::updateOrCreate(
            ['group' => 'payment', 'key' => 'fake.enabled'],
            [
                'value' => '0',
                'is_sensitive' => false,
                'description' => '模拟支付 - 启用（开启后可在线上环境使用模拟支付调试）',
            ],
        );
    }

    public function down(): void
    {
        SystemConfigModel::query()
            ->where('group', 'payment')
            ->where('key', 'fake.enabled')
            ->delete();
    }
};
