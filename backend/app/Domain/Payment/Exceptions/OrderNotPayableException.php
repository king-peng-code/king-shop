<?php

namespace App\Domain\Payment\Exceptions;

use App\Exceptions\BusinessException;

class OrderNotPayableException extends BusinessException
{
    public function __construct(string $message = '订单当前不可支付')
    {
        parent::__construct(42204, $message, 422);
    }
}
