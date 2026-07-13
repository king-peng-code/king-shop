<?php

namespace App\Domain\Payment\DTO;

final class NotifyVerifyResult
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function __construct(
        public readonly bool $verified,
        public readonly ?string $outTradeNo,
        public readonly ?string $tradeNo,
        public readonly array $rawPayload,
    ) {}

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public static function success(string $outTradeNo, ?string $tradeNo, array $rawPayload): self
    {
        return new self(true, $outTradeNo, $tradeNo, $rawPayload);
    }

    public static function failure(): self
    {
        return new self(false, null, null, []);
    }
}
