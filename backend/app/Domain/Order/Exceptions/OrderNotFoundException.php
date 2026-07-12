<?php

namespace App\Domain\Order\Exceptions;

use App\Exceptions\BusinessException;

class OrderNotFoundException extends BusinessException
{
    public function __construct(string $message = '订单不存在')
    {
        parent::__construct(404, $message, 404);
    }
}
