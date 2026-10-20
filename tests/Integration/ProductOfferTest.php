<?php

namespace Wexample\SymfonyShop\Tests\Integration;

use Wexample\SymfonyShop\Entity\ProductAvailability;
use Wexample\SymfonyShop\Entity\ProductVariation;
use Wexample\SymfonyShop\Entity\Schedule;
use Wexample\SymfonyShop\Enum\ProductOfferAvailability;
use Wexample\SymfonyShop\Enum\VariationPriceAction;
use Wexample\SymfonyShop\Service\ProductOfferService;

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

    private function offers(): ProductOfferService
    {
        return static::getContainer()->get(ProductOfferService::class);
    }
}
