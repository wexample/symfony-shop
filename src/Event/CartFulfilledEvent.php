<?php

namespace Wexample\SymfonyShop\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Wexample\SymfonyShop\Entity\Cart;

/**
 * Dispatched once, when a paid cart was delivered.
 */
class CartFulfilledEvent extends Event
{
    public function __construct(public readonly Cart $cart)
    {
    }
}
