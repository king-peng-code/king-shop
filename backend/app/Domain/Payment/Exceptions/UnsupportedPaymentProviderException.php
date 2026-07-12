<?php

namespace App\Domain\Payment\Exceptions;

use App\Exceptions\BusinessException;

class UnsupportedPaymentProviderException extends BusinessException
{
    public function __construct(string $message = '不支持的支付渠道')
    {
        parent::__construct(42203, $message, 422);
    }
}
