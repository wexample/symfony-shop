<?php

namespace Wexample\SymfonyShop\Repository;

use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyShop\Entity\CartItem;
use Wexample\SymfonyShop\Entity\ProductAvailability;

class ProductAvailabilityRepository extends AbstractRepository
{
    public static function getEntityClassName(): string
    {
        return ProductAvailability::class;
    }

    /**
     * The availabilities limiting an item: those of its product matching its
     * schedule (or any schedule) and one of its variations (or any variation).
     *
     * @return ProductAvailability[]
     */
    public function findForItem(CartItem $item): array
    {
        $matching = [];

        foreach ($item->getProduct()->getAvailabilities() as $availability) {
            $schedule = $availability->getSchedule();
            $variation = $availability->getVariation();

            if ($schedule && $schedule !== $item->getSchedule()) {
                continue;
            }

            if ($variation && ! $item->getVariations()->contains($variation)) {
                continue;
            }

            $matching[] = $availability;
        }

        return $matching;
    }
}
