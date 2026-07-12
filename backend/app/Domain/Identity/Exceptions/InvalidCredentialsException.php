<?php

namespace App\Domain\Identity\Exceptions;

use App\Exceptions\BusinessException;

class InvalidCredentialsException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(401, '手机号或密码错误', 401);
    }
}
