<?php

namespace App\Domain\SystemConfig\Exceptions;

use App\Exceptions\BusinessException;

class SensitiveConfigForbiddenException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(403, '无权修改敏感配置', 403);
    }
}
