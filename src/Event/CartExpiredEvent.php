<?php

namespace Wexample\SymfonyShop\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Wexample\SymfonyShop\Entity\Cart;

/**
 * Dispatched when an abandoned cart expires, e.g. to delete a disabled shadow user.
 */
class CartExpiredEvent extends Event
{
    public function __construct(public readonly Cart $cart)
    {
    }
}
