<?php

namespace App\Infrastructure\Payment;

use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;

class PaymentConfigReader
{
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
}
