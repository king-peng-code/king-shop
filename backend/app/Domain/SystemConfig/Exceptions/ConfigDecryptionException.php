<?php

namespace App\Domain\SystemConfig\Exceptions;

use App\Exceptions\BusinessException;

class ConfigDecryptionException extends BusinessException
{
    public function __construct(string $message = 'Failed to decrypt config value')
    {
        parent::__construct(500, $message, 500);
    }
}
