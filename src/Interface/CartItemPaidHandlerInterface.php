<?php

namespace Wexample\SymfonyShop\Interface;

use Wexample\SymfonyShop\Entity\CartItem;

/**
 * What happens when an item is paid, chosen by product type: renew a membership,
 * issue a ticket, thank a donor. Called once per paid item.
 */
interface CartItemPaidHandlerInterface
{
    public function supports(string $productType): bool;

    public function handle(CartItem $item): void;
}
