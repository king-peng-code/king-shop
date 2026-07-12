<?php

namespace App\Domain\ProxyPay\Exceptions;

use App\Exceptions\BusinessException;

class ProxyPayTokenNotFoundException extends BusinessException
{
    public function __construct(string $message = '代付链接无效')
    {
        parent::__construct(404, $message, 404);
    }
}
