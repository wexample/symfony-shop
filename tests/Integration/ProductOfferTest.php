<?php

namespace Wexample\SymfonyShop\Tests\Integration;

use Wexample\SymfonyShop\Entity\ProductAvailability;
use Wexample\SymfonyShop\Entity\ProductVariation;
use Wexample\SymfonyShop\Entity\Schedule;
use Wexample\SymfonyShop\Enum\ProductOfferAvailability;
use Wexample\SymfonyShop\Enum\VariationPriceAction;
use DateTimeImmutable;
use Wexample\SymfonyShop\Repository\ProductAvailabilityRepository;
use Wexample\SymfonyShop\Repository\StockReservationRepository;
use Wexample\SymfonyShop\Service\ProductOfferService;
use Wexample\SymfonyShop\Service\StockService;

class ProductOfferTest extends AbstractCartTestCase
{
    public function testAnOfferPerScheduleAndPerVariationChangingThePrice(): void
    {
        $product = $this->product(500, 1000, slug: 'show');
        $presenter = (new ProductVariation())->setName('role')->setValue('presenter')
            ->setPriceAction(VariationPriceAction::PriceFinal, 200);
        // Changing nothing: the product's own offer.
        $seat = (new ProductVariation())->setName('seat')->setValue('front');
        $product->addVariation($presenter)->addVariation($seat);

        $evening = (new Schedule())->setDateStart(new \DateTimeImmutable('+1 day'));
        $past = (new Schedule())->setDateStart(new \DateTimeImmutable('-1 day'));
        $this->em()->persist($evening);
        $this->em()->persist($past);
        $product
            ->addAvailability((new ProductAvailability())->setSchedule($evening)->setQuantity(3)->setLowStockThreshold(5))
            ->addAvailability((new ProductAvailability())->setSchedule($past)->setQuantity(3));
        $this->em()->flush();

        $offers = $this->offers()->getOffers($product);

        // The past schedule is left out.
        $this->assertCount(2, $offers);
        $this->assertSame([null, $presenter], [$offers[0]->variation, $offers[1]->variation]);
        $this->assertSame($evening, $offers[1]->schedule);
        $this->assertSame(550, $offers[0]->priceFinal);
        // What the cart charges for the same choice.
        $cart = $this->carts()->create();
        $this->carts()->addProduct($cart, $product, schedule: $evening, variations: [$presenter]);
        $this->assertSame($cart->calcPriceFinal(), $offers[1]->priceFinal);
        $this->assertSame(3, $offers[1]->remaining);
        $this->assertSame(ProductOfferAvailability::Limited, $offers[1]->availability);
    }

    public function testAProductWithoutChoiceIsOneOffer(): void
    {
        $product = $this->product(10000, 2000);
        $product->addAvailability((new ProductAvailability())->setQuantity(0));
        $this->em()->flush();

        $offers = $this->offers()->getOffers($product);

        $this->assertCount(1, $offers);
        $this->assertSame(12000, $offers[0]->priceFinal);
        $this->assertSame('EUR', $offers[0]->currencyCode);
        $this->assertTrue($offers[0]->isSoldOut());
    }

    public function testSoldOutOnlyWhenEveryScheduleIs(): void
    {
        $product = $this->product(1000, 0, slug: 'concert');
        $friday = (new Schedule())->setDateStart(new \DateTimeImmutable('+1 day'));
        $saturday = (new Schedule())->setDateStart(new \DateTimeImmutable('+2 days'));
        $this->em()->persist($friday);
        $this->em()->persist($saturday);
        $product
            ->addAvailability((new ProductAvailability())->setSchedule($friday)->setQuantity(0))
            ->addAvailability($left = (new ProductAvailability())->setSchedule($saturday)->setQuantity(1));
        $this->em()->flush();

        $this->assertFalse($this->offers()->isSoldOut($product));

        $left->setQuantity(0);
        $this->em()->flush();

        $this->assertTrue($this->offers()->isSoldOut($product));
    }

    public function testSoldOutOnlyWhenEveryVariationIs(): void
    {
        $product = $this->product(1000, 0, slug: 'shirt');
        $small = (new ProductVariation())->setName('size')->setValue('S');
        $large = (new ProductVariation())->setName('size')->setValue('L');
        $product->addVariation($small)->addVariation($large);
        $product->addAvailability((new ProductAvailability())->setVariation($small)->setQuantity(0));
        $this->em()->flush();

        // The large one has no stock of its own: nothing limits it.
        $this->assertFalse($this->offers()->isSoldOut($product));

        $product->addAvailability((new ProductAvailability())->setVariation($large)->setQuantity(0));
        $this->em()->flush();

        $this->assertTrue($this->offers()->isSoldOut($product));
    }

    public function testAnAvailabilitySharedByOffersIsReadOnce(): void
    {
        $product = $this->product(1000, 0, slug: 'festival');
        $friday = (new Schedule())->setDateStart(new \DateTimeImmutable('+1 day'));
        $saturday = (new Schedule())->setDateStart(new \DateTimeImmutable('+2 days'));
        $this->em()->persist($friday);
        $this->em()->persist($saturday);
        // The site's capacity, every day, and each day's own.
        $product
            ->addAvailability((new ProductAvailability())->setQuantity(4))
            ->addAvailability((new ProductAvailability())->setSchedule($friday)->setQuantity(2))
            ->addAvailability((new ProductAvailability())->setSchedule($saturday)->setQuantity(7));
        $this->em()->flush();

        $stock = new class(
            $this->em(),
            static::getContainer()->get(ProductAvailabilityRepository::class),
            static::getContainer()->get(StockReservationRepository::class),
            static::getContainer()->get('event_dispatcher'),
        ) extends StockService {
            public int $reads = 0;

            public function getAvailable(
                ProductAvailability $availability,
                ?DateTimeImmutable $now = null
            ): ?int {
                ++$this->reads;

                return parent::getAvailable($availability, $now);
            }
        };
        $offers = (new ProductOfferService($stock))->getOffers($product);

        // The tightest availability of each day wins.
        $this->assertSame([2, 4], [$offers[0]->remaining, $offers[1]->remaining]);
        // Three availabilities, three reads: the shared one is not read per day.
        $this->assertSame(3, $stock->reads);
    }

    private function offers(): ProductOfferService
    {
        return static::getContainer()->get(ProductOfferService::class);
    }
}
