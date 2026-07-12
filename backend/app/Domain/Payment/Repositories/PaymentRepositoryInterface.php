<?php

namespace App\Domain\Payment\Repositories;

use App\Domain\Payment\Entities\Payment;

interface PaymentRepositoryInterface
{
    public function findById(int $id): ?Payment;

    public function findByOutTradeNo(string $outTradeNo): ?Payment;

    public function findPendingByOrderId(int $orderId): ?Payment;

    /**
     * @return Payment[]
     */
    public function findPendingPayments(int $limit = 100): array;

    public function save(Payment $payment): Payment;
}
