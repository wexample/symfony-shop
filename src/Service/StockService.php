<?php

namespace Wexample\SymfonyShop\Service;

use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Wexample\SymfonyShop\Entity\Cart;
use Wexample\SymfonyShop\Entity\ProductAvailability;
use Wexample\SymfonyShop\Entity\StockReservation;
use Wexample\SymfonyShop\Enum\ReservationStatus;
use Wexample\SymfonyShop\Exception\OutOfStockException;
use Wexample\SymfonyShop\Repository\ProductAvailabilityRepository;
use Wexample\SymfonyShop\Repository\StockReservationRepository;

/**
 * Availability = quantity − units sold − units held by unexpired reservations.
 * Reserving locks the availability row, so two checkouts cannot both take the last unit.
 */
class StockService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductAvailabilityRepository $availabilityRepository,
        private readonly StockReservationRepository $reservationRepository,
        private readonly string $reservationTtl = 'PT30M',
    ) {
    }

    public function getAvailable(
        ProductAvailability $availability,
        ?DateTimeImmutable $now = null
    ): ?int {
        if ($availability->isUnlimited()) {
            return null;
        }

        return max(0, $availability->getQuantity()
            - $this->reservationRepository->sumHolding($availability, $now ?? new DateTimeImmutable()));
    }

    /**
     * Holds stock for every limited item of the cart, or nothing at all.
     *
     * @return StockReservation[]
     *
     * @throws OutOfStockException
     */
    public function reserveCart(Cart $cart): array
    {
        $now = new DateTimeImmutable();
        $expiresAt = $now->add(new DateInterval($this->reservationTtl));
        $reservations = [];

        // A cart going back through checkout keeps no stale hold.
        $this->releaseCart($cart, flush: false);

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            foreach ($cart->getItems() as $item) {
                foreach ($this->availabilityRepository->findForItem($item) as $availability) {
                    if ($availability->isUnlimited()) {
                        continue;
                    }

                    $this->entityManager->lock($availability, LockMode::PESSIMISTIC_WRITE);
                    $available = $this->getAvailable($availability, $now)
                        - $this->sumPending($reservations, $availability);

                    // Thrown before anything is persisted, so the entity manager stays usable.
                    if ($item->getQuantity() > $available) {
                        throw new OutOfStockException($availability, $item->getQuantity(), $available);
                    }

                    $reservations[] = (new StockReservation())
                        ->setAvailability($availability)
                        ->setCartItem($item)
                        ->setQuantity($item->getQuantity())
                        ->setExpiresAt($expiresAt);
                }
            }

            foreach ($reservations as $reservation) {
                $this->entityManager->persist($reservation);
            }

            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        return $reservations;
    }

    /**
     * The cart is paid: its holds become sales.
     */
    public function confirmCart(Cart $cart): void
    {
        foreach ($this->reservationRepository->findActiveForCart($cart) as $reservation) {
            $reservation->setStatus(ReservationStatus::Confirmed);
        }

        $this->entityManager->flush();
    }

    public function releaseCart(
        Cart $cart,
        bool $flush = true
    ): void {
        foreach ($this->reservationRepository->findActiveForCart($cart) as $reservation) {
            $reservation->setStatus(ReservationStatus::Released);
        }

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * @param StockReservation[] $reservations
     */
    private function sumPending(
        array $reservations,
        ProductAvailability $availability
    ): int {
        $sum = 0;

        foreach ($reservations as $reservation) {
            if ($reservation->getAvailability() === $availability) {
                $sum += $reservation->getQuantity();
            }
        }

        return $sum;
    }
}
