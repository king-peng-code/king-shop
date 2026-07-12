<?php

namespace App\Infrastructure\Payment\Signature;

class AlipaySigner
{
    /**
     * @param  array<string, string>  $params
     */
    public function sign(array $params, string $privateKeyPem): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === '' || $key === 'sign' || $key === 'sign_type') {
                continue;
            }
            $pairs[] = $key.'='.$value;
        }
        $content = implode('&', $pairs);
        $privateKey = openssl_pkey_get_private($this->normalizeKey($privateKeyPem, 'PRIVATE'));
        if ($privateKey === false) {
            throw new \RuntimeException('Invalid Alipay private key');
        }

        $signature = '';
        openssl_sign($content, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    /**
     * @param  array<string, string>  $params
     */
    public function verify(array $params, string $publicKeyPem): bool
    {
        $sign = $params['sign'] ?? '';
        if ($sign === '') {
            return false;
        }

        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === '' || $key === 'sign' || $key === 'sign_type') {
                continue;
            }
            $pairs[] = $key.'='.$value;
        }
        $content = implode('&', $pairs);
        $publicKey = openssl_pkey_get_public($this->normalizeKey($publicKeyPem, 'PUBLIC'));
        if ($publicKey === false) {
            return false;
        }

        return openssl_verify($content, base64_decode($sign), $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function normalizeKey(string $key, string $type): string
    {
        $key = trim($key);
        if (str_contains($key, 'BEGIN')) {
            return $key;
        }

        $wrapped = chunk_split(str_replace(["\r", "\n", ' '], '', $key), 64, "\n");

        return "-----BEGIN {$type} KEY-----\n{$wrapped}-----END {$type} KEY-----";
    }
}
