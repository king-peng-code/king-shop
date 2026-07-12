<?php

namespace App\Domain\Order\Services;

use App\Domain\Order\Exceptions\InvalidOrderTransitionException;
use App\Domain\Order\ValueObjects\OrderStatus;

class OrderStateMachine
{
    private const TRANSITIONS = [
        OrderStatus::PENDING_PAYMENT => [OrderStatus::PAID, OrderStatus::CANCELLED],
        OrderStatus::PAID => [OrderStatus::PREPARING],
        OrderStatus::PREPARING => [OrderStatus::READY],
        OrderStatus::READY => [OrderStatus::COMPLETED],
        OrderStatus::COMPLETED => [],
        OrderStatus::CANCELLED => [],
    ];

    public function transition(OrderStatus $from, OrderStatus $to): OrderStatus
    {
        $allowed = self::TRANSITIONS[$from->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw new InvalidOrderTransitionException();
        }

        return $to;
    }
}
