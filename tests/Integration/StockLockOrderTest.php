<?php

declare(strict_types=1);

namespace Wexample\SymfonyShop\Tests\Integration;

use DateTimeInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Wexample\SymfonyShop\Entity\ProductAvailability;
use Wexample\SymfonyShop\Repository\ProductAvailabilityRepository;
use Wexample\SymfonyShop\Repository\StockReservationRepository;
use Wexample\SymfonyShop\Service\StockService;

/**
 * Two carts holding the same availabilities lock them in one order, that of
 * their ids, whatever the order of their items: else each could wait for the
 * row the other holds.
 */
class StockLockOrderTest extends AbstractCartTestCase
{
    public function testAvailabilitiesAreLockedInTheOrderOfTheirIdsNotOfTheCart(): void
    {
        $first = $this->product(1000, 0, slug: 'first');
        $first->addAvailability($firstAvailability = (new ProductAvailability())->setQuantity(5));
        $this->em()->flush();
        $second = $this->product(1000, 0, slug: 'second');
        $second->addAvailability($secondAvailability = (new ProductAvailability())->setQuantity(5));
        $this->em()->flush();

        $ids = [(string) $firstAvailability->getId(), (string) $secondAvailability->getId()];
        sort($ids);
        // The availability with the greatest id goes first in the cart.
        [$later, $earlier] = $ids[1] === (string) $secondAvailability->getId()
            ? [$second, $first]
            : [$first, $second];

        $cart = $this->carts()->create();
        $this->carts()->addProduct($cart, $later);
        $this->carts()->addProduct($cart, $earlier);

        $recorder = new class($this->em()) extends EntityManagerDecorator {
            /** @var list<string> */
            public array $locked = [];

            public function lock(
                object $entity,
                LockMode|int $lockMode,
                DateTimeInterface|int|null $lockVersion = null
            ): void {
                $this->locked[] = (string) $entity->getId();
                parent::lock($entity, $lockMode, $lockVersion);
            }
        };

        $stock = new StockService(
            $recorder,
            static::getContainer()->get(ProductAvailabilityRepository::class),
            static::getContainer()->get(StockReservationRepository::class),
            static::getContainer()->get('event_dispatcher'),
        );

        $reservations = $stock->reserveCart($cart);

        $this->assertSame($ids, $recorder->locked);
        // Held in the order of the cart, as before.
        $this->assertSame($later, $reservations[0]->getAvailability()->getProduct());
        $this->assertSame(4, $stock->getAvailable($firstAvailability));
        $this->assertSame(4, $stock->getAvailable($secondAvailability));
    }
}
