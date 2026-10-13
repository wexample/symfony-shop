<?php

namespace Wexample\SymfonyShop\Tests\Integration;

use DateTimeImmutable;
use Wexample\SymfonyGeo\Entity\AbstractAddress;
use Wexample\SymfonyGeo\Entity\Country;
use Wexample\SymfonyMoney\Enum\PriceUnit;
use Wexample\SymfonyPayment\Interface\BuyerIdentifiedInterface;
use Wexample\SymfonyShop\Entity\ProductAvailability;
use Wexample\SymfonyShop\Enum\CartStatus;
use Wexample\SymfonyShop\Event\CartFulfilledEvent;
use Wexample\SymfonyShop\Event\LowStockEvent;
use Wexample\SymfonyShop\Repository\CartItemRepository;
use Wexample\SymfonyShop\Repository\StockReservationRepository;
use Wexample\SymfonyShop\Tests\Fixtures\Handler\CartEventRecorder;

class OrderLifecycleTest extends AbstractCartTestCase
{
    private function events(): CartEventRecorder
    {
        return static::getContainer()->get(CartEventRecorder::class);
    }

    private function address(string $countryCode): AbstractAddress
    {
        $country = (new Country())->setIsoAlpha2Code($countryCode)->setIsoAlpha3Code($countryCode.'X')->setIsoNumericCode('999')->setName($countryCode);
        $this->em()->persist($country);

        return (new class () extends AbstractAddress {
        })->setPostalAddress('1 Main street')->setPostCode('1000')->setCity('City')->setCountry($country);
    }

    public function testATransferWaitsForItsMoneyThenTheOrderIsDelivered(): void
    {
        $product = $this->product(1000, 2000, slug: 'chair');
        $availability = (new ProductAvailability())->setQuantity(10);
        $product->addAvailability($availability);
        $this->em()->flush();

        $cart = $this->carts()->create('user-1');
        $this->carts()->addProduct($cart, $product, quantity: 2);
        $this->checkout()->checkout($cart, 'transfer');

        $this->assertSame(CartStatus::AwaitingValidation, $cart->getStatus());
        $this->assertFalse($cart->isEditable());
        // A transfer takes days: the stock stays held that long.
        $reservation = static::getContainer()->get(StockReservationRepository::class)->findActiveForCart($cart)[0];
        $this->assertGreaterThan(new DateTimeImmutable('+6 days'), $reservation->getExpiresAt());

        $this->payments()->confirmManualPayment($cart->getPayment());
        $this->assertSame(CartStatus::Paid, $cart->getStatus());

        $this->assertTrue($this->checkout()->fulfill($cart, new DateTimeImmutable('2026-10-20')));
        $this->assertSame(CartStatus::Fulfilled, $cart->getStatus());
        $this->assertSame('2026-10-20', $cart->getDateFulfilled()->format('Y-m-d'));
        $this->assertFalse($this->checkout()->fulfill($cart));
        $this->assertSame(1, $this->events()->count(CartFulfilledEvent::class));

        // Delivered is still sold.
        $sales = static::getContainer()->get(CartItemRepository::class)->sumPaidSales($product);
        $this->assertSame(2, $sales['quantity']);
    }

    public function testACanceledTransferReopensTheCart(): void
    {
        $cart = $this->carts()->create();
        $this->carts()->addProduct($cart, $this->product());
        $this->checkout()->checkout($cart, 'transfer');

        $this->payments()->cancel($cart->getPayment());

        $this->assertSame(CartStatus::Opened, $cart->getStatus());
    }

    public function testOnlyAPaidCartIsFulfilled(): void
    {
        $cart = $this->carts()->create();
        $this->carts()->addProduct($cart, $this->product());

        $this->expectException(\LogicException::class);
        $this->checkout()->fulfill($cart);
    }

    public function testTheBuyerIsWhatTheCartWasGiven(): void
    {
        $cart = $this->carts()->create()
            ->setCustomerName('Ada Lovelace')
            ->setCustomerCompanyName('Analytical SA')
            ->setCustomerEmail('ada@example.com')
            ->setCustomerPhone('+32 2 000 00 00')
            ->setCustomerVatNumber('BE0123456789')
            ->setOrigin('phone')
            ->setDateShippingExpected(new DateTimeImmutable('2026-11-02'))
            ->setBillingAddress($this->address('BE'));

        $this->assertInstanceOf(BuyerIdentifiedInterface::class, $cart);
        $this->assertSame('Analytical SA', $cart->getBuyerCompanyName());
        $this->assertSame('+32 2 000 00 00', $cart->getBuyerPhone());
        $this->assertSame('BE0123456789', $cart->getBuyerVatNumber());
        $this->assertSame('BE', $cart->getBuyerAddress()->getCountryCode());
        $this->assertSame('phone', $cart->getOrigin());
    }

    public function testALineHasItsOwnDiscount(): void
    {
        $cart = $this->carts()->create();
        $item = $this->carts()->addProduct($cart, $this->product(10000, 2000), quantity: 2);

        $item->setPriceDiscount(1000, PriceUnit::Percent);

        // 2 × 100.00 − 10 % = 180.00, + 20 % VAT.
        $this->assertSame(21600, $item->calcPriceFinal());
        $this->assertSame(21600, $cart->calcPriceFinal());
    }

    public function testABusinessAbroadPaysNoVat(): void
    {
        $chair = $this->product(10000, 2000, slug: 'chair');
        $fixed = $this->product(10000, 2000, slug: 'fixed')->setPriceOverridden(12000);
        $cart = $this->carts()->create();
        $this->carts()->addProduct($cart, $chair);
        $line = $this->carts()->addProduct($cart, $fixed);
        $this->assertSame(24000, $cart->calcPriceFinal());

        $cart->setCustomerVatNumber('BE0123456789');
        $this->checkout()->checkout($cart, 'card', $this->address('BE'));

        $this->assertSame(0, $line->getPriceVat());
        $this->assertSame(2000, $line->getPriceVatDefault());
        // The fixed price included 20 % VAT: without it, its base remains.
        $this->assertSame(10000, $line->calcPriceFinal());
        $this->assertSame(20000, $cart->calcPriceFinal());
        $this->assertSame(20000, $this->provider()->requests[0]->amount);
    }

    public function testASaleDownToTheThresholdAsksForStock(): void
    {
        $product = $this->product(1000, 0, slug: 'mug');
        $availability = (new ProductAvailability())->setQuantity(5)->setLowStockThreshold(2);
        $product->addAvailability($availability);
        $this->em()->flush();

        $sell = function (int $quantity) use ($product): void {
            $cart = $this->carts()->create();
            $this->carts()->addProduct($cart, $product, quantity: $quantity);
            $initiation = $this->checkout()->checkout($cart, 'card');
            $this->payments()->handleNotification($this->provider()->succeed($initiation->providerReference));
        };

        $sell(2);
        $this->assertSame(0, $this->events()->count(LowStockEvent::class));

        $sell(1);
        $this->assertSame(1, $this->events()->count(LowStockEvent::class));
        $alerts = array_values(array_filter($this->events()->events, fn (object $event) => $event instanceof LowStockEvent));
        $this->assertSame(2, $alerts[0]->available);
        $this->assertSame($availability, $alerts[0]->availability);

        // Already below: no second alert.
        $sell(1);
        $this->assertSame(1, $this->events()->count(LowStockEvent::class));
    }
}
