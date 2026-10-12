<?php

namespace Wexample\SymfonyShop\Service;

use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyPayment\Interface\PayableInterface;
use Wexample\SymfonyPayment\Interface\PayableResolverInterface;
use Wexample\SymfonyShop\Entity\Cart;
use Wexample\SymfonyShop\Repository\CartRepository;

class CartPayableResolver implements PayableResolverInterface
{
    public function __construct(
        private readonly CartRepository $cartRepository,
    ) {
    }

    public function supports(string $payableType): bool
    {
        return Cart::PAYABLE_TYPE === $payableType;
    }

    public function resolve(
        string $payableType,
        string $payableId
    ): ?PayableInterface {
        return Uuid::isValid($payableId) ? $this->cartRepository->find(Uuid::fromString($payableId)) : null;
    }
}
