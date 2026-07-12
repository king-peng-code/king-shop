<?php

use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SystemConfigModel::updateOrCreate(
            ['group' => 'external_user', 'key' => 'tag_presets'],
            [
                'value' => 'VIP,企业,个人,已失效',
                'is_sensitive' => false,
                'description' => '代付人预设标签（英文逗号分隔）',
            ],
        );
    }

    public function down(): void
    {
        SystemConfigModel::where('group', 'external_user')
            ->where('key', 'tag_presets')
            ->delete();
    }
};
