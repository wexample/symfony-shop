<?php

namespace Wexample\SymfonyShop\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Wexample\SymfonyShop\Entity\ProductAvailability;

/**
 * A sale brought an availability down to its low-stock threshold: time to
 * restock. Dispatched once, by the sale that crosses it.
 */
class LowStockEvent extends Event
{
    public function __construct(
        public readonly ProductAvailability $availability,
        public readonly int $available,
    ) {
    }
}
