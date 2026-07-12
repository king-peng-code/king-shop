<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Payment\Entities\Payment;
use App\Domain\Payment\Repositories\PaymentRepositoryInterface;
use App\Domain\Payment\ValueObjects\PaymentChannel;
use App\Domain\Payment\ValueObjects\PaymentStatus;
use App\Infrastructure\Persistence\Eloquent\Models\PaymentModel;

class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    public function findById(int $id): ?Payment
    {
        $model = PaymentModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByOutTradeNo(string $outTradeNo): ?Payment
    {
        $model = PaymentModel::query()->where('out_trade_no', $outTradeNo)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findPendingByOrderId(int $orderId): ?Payment
    {
        $model = PaymentModel::query()
            ->where('order_id', $orderId)
            ->where('status', PaymentStatus::PENDING)
            ->orderByDesc('id')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findPendingPayments(int $limit = 100): array
    {
        return PaymentModel::query()
            ->where('status', PaymentStatus::PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (PaymentModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(Payment $payment): Payment
    {
        $attributes = [
            'order_id' => $payment->orderId,
            'payer_user_id' => $payment->payerUserId,
            'out_trade_no' => $payment->outTradeNo,
            'trade_no' => $payment->tradeNo,
            'amount' => $payment->amount,
            'channel' => $payment->channel->value,
            'status' => $payment->status->value,
            'paid_at' => $payment->paidAt?->format('Y-m-d H:i:s'),
            'raw_notify' => $payment->rawNotify,
        ];

        if ($payment->id === null) {
            $model = PaymentModel::query()->create($attributes);
        } else {
            $model = PaymentModel::query()->findOrFail($payment->id);
            $model->fill($attributes);
            $model->save();
        }

        return $this->toDomain($model->fresh());
    }

    private function toDomain(PaymentModel $model): Payment
    {
        return new Payment(
            id: $model->id,
            orderId: $model->order_id,
            payerUserId: $model->payer_user_id,
            outTradeNo: $model->out_trade_no,
            tradeNo: $model->trade_no,
            amount: $model->amount,
            channel: PaymentChannel::fromString($model->channel),
            status: PaymentStatus::fromString($model->status),
            paidAt: $model->paid_at ? \DateTimeImmutable::createFromMutable($model->paid_at) : null,
            rawNotify: $model->raw_notify,
        );
    }
}
