<?php

namespace App\Domain\Catalog\Exceptions;

use App\Exceptions\BusinessException;

class CategoryNotFoundException extends BusinessException
{
    public function __construct(string $message = '分类不存在')
    {
        parent::__construct(404, $message, 404);
    }
}
