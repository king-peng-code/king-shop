<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class SystemConfigModel extends Model
{
    protected $table = 'system_configs';

    protected $fillable = [
        'group',
        'key',
        'value',
        'is_sensitive',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
        ];
    }
}
