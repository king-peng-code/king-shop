<?php

namespace App\Infrastructure\Payment;

use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;

class PaymentConfigReader
{
    public const ALIPAY = 'alipay';
    public const WECHAT = 'wechat';

    public const MODE_SANDBOX = 'sandbox';
    public const MODE_PRODUCTION = 'production';

    /** @var array<string, list<string>> */
    private const REQUIRED_KEYS = [
        self::ALIPAY => ['alipay.app_id', 'alipay.private_key', 'alipay.public_key'],
        self::WECHAT => ['wechat.app_id', 'wechat.mch_id', 'wechat.api_key'],
    ];

    /** 支付宝网关地址 */
    private const ALIPAY_GATEWAY_URLS = [
        self::MODE_SANDBOX => 'https://openapi-sandbox.dl.alipaydev.com/gateway.do',
        self::MODE_PRODUCTION => 'https://openapi.dl.alipay.com/gateway.do',
    ];

    /** 微信支付网关地址 */
    private const WECHAT_UNIFIED_ORDER_URLS = [
        self::MODE_SANDBOX => 'https://api.mch.weixin.qq.com/sandboxnew/pay/unifiedorder',
        self::MODE_PRODUCTION => 'https://api.mch.weixin.qq.com/pay/unifiedorder',
    ];

    private const WECHAT_ORDER_QUERY_URLS = [
        self::MODE_SANDBOX => 'https://api.mch.weixin.qq.com/sandboxnew/pay/orderquery',
        self::MODE_PRODUCTION => 'https://api.mch.weixin.qq.com/pay/orderquery',
    ];

    public function __construct(
        private readonly SystemConfigRepositoryInterface $configRepository,
    ) {}

    public function provider(): string
    {
        return $this->get('provider', 'alipay_sandbox');
    }

    public function get(string $key, string $default = ''): string
    {
        $config = $this->configRepository->findByGroupAndKey('payment', $key);

        return $config?->value ?? $default;
    }

    public function notifyBaseUrl(): string
    {
        return rtrim(config('app.url'), '/');
    }

    /**
     * Get the mode (sandbox|production) for a given channel.
     */
    public function mode(string $channel): string
    {
        $key = match ($channel) {
            'alipay_sandbox' => 'alipay.mode',
            'wechat' => 'wechat.mode',
            default => null,
        };

        if ($key === null) {
            return self::MODE_SANDBOX;
        }

        $value = $this->get($key, self::MODE_SANDBOX);

        return match ($value) {
            self::MODE_PRODUCTION => self::MODE_PRODUCTION,
            default => self::MODE_SANDBOX,
        };
    }

    public function isProduction(string $channel): bool
    {
        return $this->mode($channel) === self::MODE_PRODUCTION;
    }

    public function isSandbox(string $channel): bool
    {
        return $this->mode($channel) === self::MODE_SANDBOX;
    }

    /**
     * Get the Alipay gateway URL based on the configured mode.
     */
    public function alipayGatewayUrl(): string
    {
        $mode = $this->mode('alipay_sandbox');

        return self::ALIPAY_GATEWAY_URLS[$mode] ?? self::ALIPAY_GATEWAY_URLS[self::MODE_SANDBOX];
    }

    /**
     * Get the WeChat unified order URL based on the configured mode.
     */
    public function wechatUnifiedOrderUrl(): string
    {
        $mode = $this->mode('wechat');

        return self::WECHAT_UNIFIED_ORDER_URLS[$mode] ?? self::WECHAT_UNIFIED_ORDER_URLS[self::MODE_SANDBOX];
    }

    /**
     * Get the WeChat order query URL based on the configured mode.
     */
    public function wechatOrderQueryUrl(): string
    {
        $mode = $this->mode('wechat');

        return self::WECHAT_ORDER_QUERY_URLS[$mode] ?? self::WECHAT_ORDER_QUERY_URLS[self::MODE_SANDBOX];
    }

    public function isEnabled(string $channel): bool
    {
        $key = match ($channel) {
            'alipay_sandbox' => 'alipay.enabled',
            'wechat' => 'wechat.enabled',
            'fake' => 'fake.enabled',
            default => null,
        };

        if ($key === null) {
            return false;
        }

        return $this->get($key, '0') === '1';
    }

    /**
     * Check if a channel has all required config keys filled.
     */
    public function isConfigured(string $channel): bool
    {
        $group = match ($channel) {
            'alipay_sandbox' => self::ALIPAY,
            'wechat' => self::WECHAT,
            'fake' => null,
            default => null,
        };

        // Channels without required keys (e.g. fake) are always configured
        if ($group === null) {
            return $channel === 'fake';
        }

        foreach (self::REQUIRED_KEYS[$group] as $key) {
            if ($this->get($key, '') === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a channel is both enabled and properly configured.
     */
    public function isAvailable(string $channel): bool
    {
        return $this->isEnabled($channel) && $this->isConfigured($channel);
    }
}
