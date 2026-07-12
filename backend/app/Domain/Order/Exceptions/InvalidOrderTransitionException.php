<?php

namespace App\Domain\Order\Exceptions;

use App\Exceptions\BusinessException;

class InvalidOrderTransitionException extends BusinessException
{
    public function __construct(string $message = '非法的订单状态转换')
    {
        parent::__construct(42201, $message, 422);
    }
}
