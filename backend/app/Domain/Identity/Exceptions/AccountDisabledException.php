<?php

namespace App\Domain\Identity\Exceptions;

use App\Exceptions\BusinessException;

class AccountDisabledException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(403, '账号已禁用', 403);
    }
}
