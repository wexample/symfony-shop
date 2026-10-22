<?php

namespace Wexample\SymfonyShop\Service;

use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Wexample\SymfonyShop\Entity\Cart;
use Wexample\SymfonyShop\Entity\ProductAvailability;
use Wexample\SymfonyShop\Entity\StockReservation;
use Wexample\SymfonyShop\Enum\ReservationStatus;
use Wexample\SymfonyShop\Event\LowStockEvent;
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
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $reservationTtl = 'PT30M',
        private readonly string $validationTtl = 'P7D',
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
     * The cart waits for a payment made by hand, which takes days rather than
     * minutes: its holds last until the money can have arrived.
     */
    public function holdCart(Cart $cart): void
    {
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval($this->validationTtl));

        foreach ($this->reservationRepository->findActiveForCart($cart) as $reservation) {
            $reservation->setExpiresAt($expiresAt);
        }

        $this->entityManager->flush();
    }

    /**
     * The cart is paid: its holds become sales. A sale bringing an availability
     * down to its threshold raises LowStockEvent.
     */
    public function confirmCart(Cart $cart): void
    {
        $reservations = $this->reservationRepository->findActiveForCart($cart);

        foreach ($reservations as $reservation) {
            $reservation->setStatus(ReservationStatus::Confirmed);
        }

        $this->entityManager->flush();

        foreach ($reservations as $reservation) {
            $availability = $reservation->getAvailability();
            $threshold = $availability->getLowStockThreshold();

            if (null === $threshold) {
                continue;
            }

            // The hold already counted: before this sale, its units were still there.
            $available = $this->getAvailable($availability);

            if ($available <= $threshold && $available + $reservation->getQuantity() > $threshold) {
                $this->eventDispatcher->dispatch(new LowStockEvent($availability, $available));
            }
        }
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
