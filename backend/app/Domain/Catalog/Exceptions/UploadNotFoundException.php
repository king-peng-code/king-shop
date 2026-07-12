<?php

namespace App\Domain\Catalog\Exceptions;

use App\Exceptions\BusinessException;

class UploadNotFoundException extends BusinessException
{
    public function __construct(string $message = '上传文件不存在')
    {
        parent::__construct(404, $message, 404);
    }
}
