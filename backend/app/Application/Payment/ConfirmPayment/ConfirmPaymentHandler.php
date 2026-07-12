<?php

namespace App\Application\Payment\ConfirmPayment;

use App\Application\Order\MarkOrderPaid\MarkOrderPaidHandler;
use App\Domain\Payment\Entities\Payment;
use App\Domain\Payment\Exceptions\PaymentNotFoundException;
use App\Domain\Payment\Repositories\PaymentRepositoryInterface;
use App\Domain\Payment\ValueObjects\PaymentStatus;
use Illuminate\Support\Facades\DB;

class ConfirmPaymentHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly MarkOrderPaidHandler $markOrderPaidHandler,
    ) {}

    /**
     * @param  array<string, mixed>  $rawNotify
     */
    public function handle(
        string $outTradeNo,
        string $tradeNo,
        array $rawNotify,
        ?int $paidByUserId = null,
    ): Payment {
        return DB::transaction(function () use ($outTradeNo, $tradeNo, $rawNotify, $paidByUserId): Payment {
            $payment = $this->paymentRepository->findByOutTradeNo($outTradeNo);

            if ($payment === null) {
                throw new PaymentNotFoundException();
            }

            if ($payment->status->isSuccess()) {
                return $payment;
            }

            $paidAt = new \DateTimeImmutable;

            $updatedPayment = $this->paymentRepository->save(new Payment(
                id: $payment->id,
                orderId: $payment->orderId,
                outTradeNo: $payment->outTradeNo,
                tradeNo: $tradeNo,
                amount: $payment->amount,
                channel: $payment->channel,
                status: PaymentStatus::fromString(PaymentStatus::SUCCESS),
                paidAt: $paidAt,
                rawNotify: $rawNotify,
            ));

            $this->markOrderPaidHandler->handle(
                orderId: $payment->orderId,
                paidByUserId: $paidByUserId,
                paidAt: $paidAt,
            );

            return $updatedPayment;
        });
    }
}
