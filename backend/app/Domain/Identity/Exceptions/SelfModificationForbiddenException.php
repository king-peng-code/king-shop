<?php

namespace App\Domain\Identity\Exceptions;

use App\Exceptions\BusinessException;

class SelfModificationForbiddenException extends BusinessException
{
    public function __construct(string $message = '不能对自己执行此操作')
    {
        parent::__construct(403, $message, 403);
    }
}
