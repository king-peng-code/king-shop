<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderModel extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'order_no', 'user_id', 'total_amount', 'status', 'payment_method',
        'paid_by_user_id', 'paid_at', 'remark', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'paid_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItemModel::class, 'order_id');
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}
