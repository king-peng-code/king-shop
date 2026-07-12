<?php

namespace App\Infrastructure\Encryption;

use App\Domain\SystemConfig\Exceptions\ConfigDecryptionException;
use App\Domain\SystemConfig\Services\ConfigEncryptionInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class LaravelConfigEncryption implements ConfigEncryptionInterface
{
    public function encrypt(string $plainText): string
    {
        return Crypt::encryptString($plainText);
    }

    public function decrypt(string $cipherText): string
    {
        try {
            return Crypt::decryptString($cipherText);
        } catch (DecryptException) {
            throw new ConfigDecryptionException;
        }
    }
}
