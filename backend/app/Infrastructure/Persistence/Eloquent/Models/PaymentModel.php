<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentModel extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'order_id', 'out_trade_no', 'trade_no', 'amount', 'channel',
        'status', 'paid_at', 'raw_notify',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'raw_notify' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }
}
