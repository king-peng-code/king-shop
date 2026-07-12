<?php

namespace App\Domain\Identity\Exceptions;

use App\Exceptions\BusinessException;

class UserNotFoundException extends BusinessException
{
    public function __construct(string $message = '用户不存在')
    {
        parent::__construct(404, $message, 404);
    }
}
