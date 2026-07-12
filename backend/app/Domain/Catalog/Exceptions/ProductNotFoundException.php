<?php

namespace App\Domain\Catalog\Exceptions;

use App\Exceptions\BusinessException;

class ProductNotFoundException extends BusinessException
{
    public function __construct(string $message = '商品不存在')
    {
        parent::__construct(404, $message, 404);
    }
}
