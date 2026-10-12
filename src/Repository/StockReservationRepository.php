<?php

namespace Wexample\SymfonyShop\Repository;

use DateTimeImmutable;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Wexample\SymfonyShop\Entity\Cart;
use Wexample\SymfonyShop\Entity\ProductAvailability;
use Wexample\SymfonyShop\Entity\StockReservation;
use Wexample\SymfonyShop\Enum\ReservationStatus;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;

class StockReservationRepository extends AbstractRepository
{
    public static function getEntityClassName(): string
    {
        return StockReservation::class;
    }

    /**
     * Units sold or held right now.
     */
    public function sumHolding(
        ProductAvailability $availability,
        DateTimeImmutable $now
    ): int {
        return (int) $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.quantity), 0)')
            ->where('r.availability = :availability')
            ->andWhere('r.status = :confirmed OR (r.status = :active AND r.expiresAt > :now)')
            ->setParameter('availability', $availability->getId(), UuidType::NAME)
            ->setParameter('confirmed', ReservationStatus::Confirmed->value)
            ->setParameter('active', ReservationStatus::Active->value)
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return StockReservation[]
     */
    public function findActiveForCart(Cart $cart): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.cartItem', 'i')
            ->where('i.cart = :cart')
            ->andWhere('r.status = :active')
            ->setParameter('cart', $cart->getId(), UuidType::NAME)
            ->setParameter('active', ReservationStatus::Active->value)
            ->getQuery()
            ->getResult();
    }
}
