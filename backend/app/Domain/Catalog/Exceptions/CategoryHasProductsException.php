<?php

namespace App\Domain\Catalog\Exceptions;

use App\Exceptions\BusinessException;

class CategoryHasProductsException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(40901, '分类下存在商品，无法删除', 409);
    }
}
