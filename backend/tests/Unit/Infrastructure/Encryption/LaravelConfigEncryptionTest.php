<?php

namespace Tests\Unit\Infrastructure\Encryption;

use App\Domain\SystemConfig\Exceptions\ConfigDecryptionException;
use App\Infrastructure\Encryption\LaravelConfigEncryption;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LaravelConfigEncryptionTest extends TestCase
{
    #[Test]
    public function encrypt_and_decrypt_round_trip(): void
    {
        $encryption = new LaravelConfigEncryption;

        $cipher = $encryption->encrypt('内部下午茶');
        $plain = $encryption->decrypt($cipher);

        $this->assertNotSame('内部下午茶', $cipher);
        $this->assertSame('内部下午茶', $plain);
    }

    #[Test]
    public function decrypt_fails_with_different_app_key(): void
    {
        $encryption = new LaravelConfigEncryption;
        $cipher = $encryption->encrypt('secret-value');

        Config::set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstances();

        $this->expectException(ConfigDecryptionException::class);
        (new LaravelConfigEncryption)->decrypt($cipher);
    }
}
