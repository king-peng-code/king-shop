<?php

namespace App\Domain\Identity\Exceptions;

use App\Exceptions\BusinessException;

class ForbiddenRoleAssignmentException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(403, '无权分配该角色', 403);
    }
}
