<?php

namespace App\Domain\Payment\DTO;

final class PaymentCreateResult
{
    /**
     * @param  array<string, mixed>  $payParams
     */
    public function __construct(
        public readonly string $outTradeNo,
        public readonly array $payParams,
    ) {}
}
