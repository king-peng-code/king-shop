<?php

namespace App\Domain\Order\Services;

interface OrderNumberGeneratorInterface
{
    public function generate(): string;
}
