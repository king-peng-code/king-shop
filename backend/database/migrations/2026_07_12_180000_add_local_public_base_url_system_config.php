<?php

use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SystemConfigModel::query()->updateOrCreate(
            ['group' => 'storage', 'key' => 'local.public_base_url'],
            [
                'value' => '',
                'is_sensitive' => false,
                'description' => '图片公开访问域名',
            ],
        );
    }

    public function down(): void
    {
        SystemConfigModel::query()
            ->where('group', 'storage')
            ->where('key', 'local.public_base_url')
            ->delete();
    }
};
