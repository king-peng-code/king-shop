<?php

namespace App\Domain\ProxyPay\Repositories;

use App\Domain\ProxyPay\Entities\ProxyPayToken;

interface ProxyPayTokenRepositoryInterface
{
    public function findByToken(string $token): ?ProxyPayToken;

    public function findActiveByOrderId(int $orderId): ?ProxyPayToken;

    public function save(ProxyPayToken $token): ProxyPayToken;
}
