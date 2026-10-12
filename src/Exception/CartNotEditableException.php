<?php

namespace Wexample\SymfonyShop\Exception;

use Wexample\SymfonyShop\Entity\Cart;

class CartNotEditableException extends \LogicException
{
    public function __construct(public readonly Cart $cart)
    {
        parent::__construct(sprintf('Cart %s is %s: its items cannot change.', $cart->getId(), $cart->getStatus()->value));
    }
}
