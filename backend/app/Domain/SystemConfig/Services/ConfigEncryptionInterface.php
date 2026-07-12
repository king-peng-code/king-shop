<?php

namespace App\Domain\SystemConfig\Services;

interface ConfigEncryptionInterface
{
    public function encrypt(string $plainText): string;

    public function decrypt(string $cipherText): string;
}
