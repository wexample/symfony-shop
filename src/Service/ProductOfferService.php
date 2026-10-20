<?php

namespace Wexample\SymfonyShop\Service;

use Wexample\SymfonyShop\Class\ProductOffer;
use Wexample\SymfonyShop\Entity\CartItem;
use Wexample\SymfonyShop\Entity\Product;
use Wexample\SymfonyShop\Entity\ProductVariation;
use Wexample\SymfonyShop\Entity\Schedule;
use Wexample\SymfonyShop\Enum\ProductOfferAvailability;
use Wexample\SymfonyShop\Enum\VariationPriceAction;

/**
 * What a product offers whoever has no cart yet: the schedules it can be
 * bought for, at which price, and how many are left — read as the reservation
 * reads them: every availability of the product matching the schedule (or
 * any) and the variation (or any), the tightest one winning.
 *
 * The catalogue price: a PriceResolverInterface decides for a cart (member
 * prices, customer groups), which a visitor does not have.
 */
class ProductOfferService
{
    public function __construct(
        private readonly StockService $stock,
    ) {
    }

    /**
     * One offer per schedule still to come and per variation that changes
     * something — its own price or its own stock —, a variation being chosen
     * one at a time; plus the product's own offer whenever it can be bought
     * without such a variation — none, or one changing nothing (a spectator
     * where a presenter pays).
     *
     * @return list<ProductOffer>
     */
    public function getOffers(Product $product): array
    {
        $variations = [];
        $plain = $product->getVariations()->isEmpty();

        foreach ($product->getVariations() as $variation) {
            if (VariationPriceAction::None !== $variation->getPriceAction() || $this->hasOwnStock($product, $variation)) {
                $variations[] = $variation;
            } else {
                $plain = true;
            }
        }

        if ($plain) {
            array_unshift($variations, null);
        }

        $offers = [];

        foreach ($this->getSchedules($product) ?: [null] as $schedule) {
            foreach ($variations as $variation) {
                $offers[] = $this->getOffer($product, $schedule, $variation);
            }
        }

        return $offers;
    }

    public function getOffer(
        Product $product,
        ?Schedule $schedule = null,
        ?ProductVariation $variation = null,
    ): ProductOffer {
        [$remaining, $threshold] = $this->readStock($product, $schedule, $variation);

        return new ProductOffer(
            $product,
            $schedule,
            $variation,
            $this->getPriceFinal($product, $variation),
            $product->getCurrencyCode(),
            $remaining,
            match (true) {
                0 === $remaining => ProductOfferAvailability::SoldOut,
                null !== $remaining && null !== $threshold && $remaining <= $threshold => ProductOfferAvailability::Limited,
                default => ProductOfferAvailability::Available,
            },
        );
    }

    /**
     * The price a cart would charge at the product's own VAT rate, the
     * variation applied: computed by a cart item never stored, so that it is
     * the cart's own computation. Null for a free amount.
     */
    public function getPriceFinal(
        Product $product,
        ?ProductVariation $variation = null,
    ): ?int {
        if ($product->isFreeAmount()) {
            return null;
        }

        $item = (new CartItem())->setProduct($product);

        if ($variation) {
            $item->addVariation($variation);
        }

        return $item->calcPriceFinal();
    }

    /**
     * The schedules still to come it can be bought for, soonest first.
     *
     * @return list<Schedule>
     */
    public function getSchedules(Product $product): array
    {
        $schedules = [];

        foreach ($product->getAvailabilities() as $availability) {
            $schedule = $availability->getSchedule();

            if ($schedule && ! $schedule->isPast()) {
                $schedules[spl_object_id($schedule)] = $schedule;
            }
        }

        $schedules = array_values($schedules);
        usort($schedules, static fn (Schedule $a, Schedule $b) => $a->getDateStart() <=> $b->getDateStart());

        return $schedules;
    }

    /**
     * How many are left, or null when nothing limits it.
     */
    public function getRemaining(
        Product $product,
        ?Schedule $schedule = null,
        ?ProductVariation $variation = null,
    ): ?int {
        return $this->readStock($product, $schedule, $variation)[0];
    }

    /**
     * Nothing left to buy, whatever is chosen: every schedule and every
     * variation it is sold with is sold out.
     */
    public function isSoldOut(Product $product): bool
    {
        $schedules = $this->getSchedules($product) ?: [null];
        $variations = $product->getVariations()->toArray() ?: [null];

        foreach ($schedules as $schedule) {
            foreach ($variations as $variation) {
                if (0 !== $this->getRemaining($product, $schedule, $variation)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array{?int, ?int} what is left, and the low-stock threshold of
     *                           the availability leaving the least
     */
    private function readStock(
        Product $product,
        ?Schedule $schedule,
        ?ProductVariation $variation,
    ): array {
        $remaining = null;
        $threshold = null;

        foreach ($product->getAvailabilities() as $availability) {
            if ($availability->getSchedule() && $availability->getSchedule() !== $schedule) {
                continue;
            }

            if ($availability->getVariation() && $availability->getVariation() !== $variation) {
                continue;
            }

            $available = $this->stock->getAvailable($availability);

            if (null !== $available && (null === $remaining || $available < $remaining)) {
                $remaining = $available;
                $threshold = $availability->getLowStockThreshold();
            }
        }

        return [$remaining, $threshold];
    }

    private function hasOwnStock(Product $product, ProductVariation $variation): bool
    {
        return $product->getAvailabilities()->exists(
            static fn ($key, $availability): bool => $availability->getVariation() === $variation
        );
    }
}
