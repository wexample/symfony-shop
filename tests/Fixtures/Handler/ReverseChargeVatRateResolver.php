<?php

namespace Wexample\SymfonyShop\Tests\Fixtures\Handler;

use Wexample\SymfonyMoney\Interface\VatRateResolverInterface;

/**
 * What symfony-accounting decides, cut down: a seller in France charges no VAT
 * to a business elsewhere that gives a VAT number.
 */
class ReverseChargeVatRateResolver implements VatRateResolverInterface
{
    public function resolveVatRate(
        int $rate,
        bool $goods,
        ?string $buyerCountryCode,
        ?string $buyerVatNumber
    ): int {
        return null !== $buyerVatNumber && null !== $buyerCountryCode && 'FR' !== $buyerCountryCode ? 0 : $rate;
    }
}
