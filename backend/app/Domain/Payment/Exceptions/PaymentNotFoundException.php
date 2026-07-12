<?php

namespace App\Domain\Payment\Exceptions;

use App\Exceptions\BusinessException;

class PaymentNotFoundException extends BusinessException
{
    public function __construct(string $message = '支付记录不存在')
    {
        parent::__construct(404, $message, 404);
    }
}
