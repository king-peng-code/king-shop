<?php

namespace App\Domain\ProxyPay\Exceptions;

use App\Exceptions\BusinessException;

class OrderNotProxyPayException extends BusinessException
{
    public function __construct(string $message = '该订单不是代付订单')
    {
        parent::__construct(42206, $message, 422);
    }
}
