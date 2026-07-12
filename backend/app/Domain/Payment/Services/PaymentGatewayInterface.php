<?php

namespace App\Domain\Payment\Services;

use App\Domain\Order\Entities\Order;
use App\Domain\Payment\DTO\NotifyVerifyResult;
use App\Domain\Payment\DTO\PaymentCreateResult;
use App\Domain\Payment\DTO\PaymentQueryResult;
use App\Domain\Payment\Entities\Payment;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    public function channel(): string;

    public function createPayment(Payment $payment, Order $order, array $options = []): PaymentCreateResult;

    public function queryPayment(string $outTradeNo): PaymentQueryResult;

    public function verifyNotify(Request $request): NotifyVerifyResult;
}
