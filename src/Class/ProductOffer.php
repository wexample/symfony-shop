<?php

namespace Wexample\SymfonyShop\Class;

use Wexample\SymfonyShop\Entity\Product;
use Wexample\SymfonyShop\Entity\ProductVariation;
use Wexample\SymfonyShop\Entity\Schedule;
use Wexample\SymfonyShop\Enum\ProductOfferAvailability;

/**
 * One thing a visitor can buy of a product: for a schedule, with a variation,
 * at a price, with what is left of it. What a product page, a feed or the
 * search engines show alike, computed once by ProductOfferService.
 */
final readonly class ProductOffer
{
    public function __construct(
        public Product $product,
        public ?Schedule $schedule,
        public ?ProductVariation $variation,
        /** VAT included, in minor units; null for a free amount, chosen by the buyer. */
        public ?int $priceFinal,
        public string $currencyCode,
        /** How many are left; null when nothing limits it. */
        public ?int $remaining,
        public ProductOfferAvailability $availability,
    ) {
    }

    public function isSoldOut(): bool
    {
        return ProductOfferAvailability::SoldOut === $this->availability;
    }
}
