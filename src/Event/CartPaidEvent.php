<?php

namespace Wexample\SymfonyShop\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Wexample\SymfonyShop\Entity\Cart;

/**
 * Dispatched once, when a cart's payment succeeded. Invoices, mails and
 * memberships are listeners of this event, not package code.
 */
class CartPaidEvent extends Event
{
    public function __construct(public readonly Cart $cart)
    {
    }
}
