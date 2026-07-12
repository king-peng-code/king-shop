<?php

namespace App\Domain\Order\Exceptions;

use App\Exceptions\BusinessException;

class OrderAccessDeniedException extends BusinessException
{
    public function __construct(string $message = '无权访问该订单')
    {
        parent::__construct(403, $message, 403);
    }
}
