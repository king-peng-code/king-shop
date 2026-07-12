<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ProxyPayTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProxyPayTokenModel extends Model
{
    /** @use HasFactory<ProxyPayTokenFactory> */
    use HasFactory;

    protected $table = 'proxy_pay_tokens';

    protected $fillable = [
        'order_id', 'token', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }

    protected static function newFactory(): ProxyPayTokenFactory
    {
        return ProxyPayTokenFactory::new();
    }
}
