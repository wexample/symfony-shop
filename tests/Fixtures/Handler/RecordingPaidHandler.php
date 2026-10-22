<?php

namespace Wexample\SymfonyShop\Tests\Fixtures\Handler;

use Wexample\SymfonyShop\Entity\CartItem;
use Wexample\SymfonyShop\Interface\CartItemPaidHandlerInterface;

class RecordingPaidHandler implements CartItemPaidHandlerInterface
{
    /** @var list<CartItem> */
    public array $handled = [];

    public function supports(string $productType): bool
    {
        return 'membership' === $productType;
    }

    public function handle(CartItem $item): void
    {
        $this->handled[] = $item;
    }
}
