<?php

namespace App\Domain\Payment\Exceptions;

use App\Exceptions\BusinessException;

class InvalidPaymentSignatureException extends BusinessException
{
    public function __construct(string $message = '支付回调验签失败')
    {
        parent::__construct(42202, $message, 422);
    }
}
