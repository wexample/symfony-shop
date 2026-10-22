<?php

namespace Wexample\SymfonyShop\Tests\Fixtures\Handler;

use Wexample\SymfonyShop\Entity\Cart;
use Wexample\SymfonyShop\Entity\Product;
use Wexample\SymfonyShop\Interface\PriceResolverInterface;

/**
 * Members (owner "member-*") pay half price.
 */
class MemberPriceResolver implements PriceResolverInterface
{
    public function resolvePrice(Product $product, Cart $cart): ?int
    {
        return str_starts_with((string) $cart->getOwnerReference(), 'member-')
            ? intdiv((int) $product->getPriceRaw(), 2)
            : null;
    }
}
