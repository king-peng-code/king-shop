<?php

namespace App\Domain\ProxyPay\Exceptions;

use App\Exceptions\BusinessException;

class ProxyPayLinkExpiredException extends BusinessException
{
    public function __construct(string $message = '代付链接已过期')
    {
        parent::__construct(42205, $message, 422);
    }
}
