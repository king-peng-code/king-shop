<?php

namespace App\Infrastructure\Payment\Signature;

class WechatSigner
{
    /**
     * @param  array<string, string>  $params
     */
    public function sign(array $params, string $apiKey): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === '' || $key === 'sign') {
                continue;
            }
            $pairs[] = $key.'='.$value;
        }
        $pairs[] = 'key='.$apiKey;

        return strtoupper(md5(implode('&', $pairs)));
    }

    /**
     * @param  array<string, string>  $params
     */
    public function verify(array $params, string $apiKey): bool
    {
        $sign = $params['sign'] ?? '';
        if ($sign === '') {
            return false;
        }
        unset($params['sign']);
        $expected = $this->sign($params, $apiKey);

        return hash_equals($expected, $sign);
    }
}
