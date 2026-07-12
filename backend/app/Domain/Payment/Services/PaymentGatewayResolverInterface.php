<?php

namespace App\Domain\Payment\Services;

interface PaymentGatewayResolverInterface
{
    public function resolve(?string $channel = null): PaymentGatewayInterface;
}
