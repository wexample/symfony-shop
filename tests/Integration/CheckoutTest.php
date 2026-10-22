<?php

namespace Wexample\SymfonyShop\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Wexample\SymfonyGeo\Entity\AbstractAddress;
use Wexample\SymfonyGeo\Entity\Country;
use Wexample\SymfonyPayment\Enum\PaymentStatus;
use Wexample\SymfonyShop\Entity\ProductAvailability;
use Wexample\SymfonyShop\Entity\Schedule;
use Wexample\SymfonyShop\Enum\CartStatus;
use Wexample\SymfonyShop\Event\CartExpiredEvent;
use Wexample\SymfonyShop\Event\CartPaidEvent;
use Wexample\SymfonyShop\Exception\CartNotEditableException;
use Wexample\SymfonyShop\Exception\OutOfStockException;
use Wexample\SymfonyShop\Repository\CartItemRepository;
use Wexample\SymfonyShop\Tests\Fixtures\Handler\CartEventRecorder;
use Wexample\SymfonyShop\Tests\Fixtures\Handler\RecordingPaidHandler;

class CheckoutTest extends AbstractCartTestCase
{
    private function events(): CartEventRecorder
    {
        return static::getContainer()->get(CartEventRecorder::class);
    }

    public function testSuccessfulCheckout(): void
    {
        $membership = $this->product(3000, 0, 'membership', 'membership');
        $cart = $this->carts()->create('user-1');
        $this->carts()->addProduct($cart, $membership);

        $belgium = (new Country())->setIsoAlpha2Code('BE')->setIsoAlpha3Code('BEL')->setIsoNumericCode('056')->setName('Belgium');
        $this->em()->persist($belgium);
        $address = (new class () extends AbstractAddress {
        })->setPostalAddress('Rue Neuve 1')->setPostCode('1000')->setCity('Bruxelles')->setCountry($belgium);
        $initiation = $this->checkout()->checkout($cart, 'card', $address);

        $this->assertSame(CartStatus::WaitingPayment, $cart->getStatus());
        $this->assertSame(3000, $this->provider()->requests[0]->amount);
        $this->assertSame('BE', $cart->getBillingAddress()->getCountryCode());
        // Written down: the buyer's address may change, the order's does not.
        $address->setCity('Liège');
        $this->assertSame('Bruxelles', $cart->getBillingAddress()->getCity());
        $this->assertNull($cart->getShippingAddress());

        // Frozen while the payment runs.
        try {
            $this->carts()->addProduct($cart, $membership);
            $this->fail('A cart waiting for payment must refuse new items.');
        } catch (CartNotEditableException) {
        }

        $notification = $this->provider()->succeed($initiation->providerReference);
        $this->payments()->handleNotification($notification);

        $this->assertSame(CartStatus::Paid, $cart->getStatus());
        $this->assertNotNull($cart->getDatePaid());
        $this->assertSame(1, $this->events()->count(CartPaidEvent::class));
        $this->assertCount(1, static::getContainer()->get(RecordingPaidHandler::class)->handled);

        // A replayed success changes nothing.
        $this->payments()->handleNotification($notification);
        $this->assertSame(1, $this->events()->count(CartPaidEvent::class));
        $this->assertCount(1, static::getContainer()->get(RecordingPaidHandler::class)->handled);

        $sales = static::getContainer()->get(CartItemRepository::class)->sumPaidSales($membership);
        $this->assertSame(['quantity' => 1, 'amount' => 3000, 'count' => 1], $sales);
    }

    public function testFailedPaymentReopensTheCart(): void
    {
        $cart = $this->carts()->create();
        $this->carts()->addProduct($cart, $this->product());
        $initiation = $this->checkout()->checkout($cart, 'card');

        $this->payments()->handleNotification($this->provider()->fail($initiation->providerReference));

        $this->assertSame(CartStatus::Opened, $cart->getStatus());
        $this->assertSame(PaymentStatus::Failed, $cart->getPayment()->getStatus());
    }

    public function testFreeCartIsPaidWithoutProvider(): void
    {
        $cart = $this->carts()->create();
        $this->carts()->addProduct($cart, $this->product(0, 0));

        $this->assertNull($this->checkout()->checkout($cart, 'card'));
        $this->assertSame(CartStatus::Paid, $cart->getStatus());
        $this->assertSame([], $this->provider()->requests);
    }

    public function testReservation(): void
    {
        $product = $this->product(1000, 0, slug: 'seat');
        $schedule = (new Schedule())->setDateStart(new \DateTimeImmutable('+1 month'));
        $availability = (new ProductAvailability())->setSchedule($schedule)->setQuantity(1);
        $product->addAvailability($availability);
        $this->em()->persist($schedule);
        $this->em()->flush();

        $first = $this->carts()->create();
        $this->carts()->addProduct($first, $product, schedule: $schedule);
        $second = $this->carts()->create();
        $this->carts()->addProduct($second, $product, schedule: $schedule);

        $initiation = $this->checkout()->checkout($first, 'card');
        $this->assertSame(0, $this->stock()->getAvailable($availability));

        try {
            $this->checkout()->checkout($second, 'card');
            $this->fail('The last seat is held by the first cart.');
        } catch (OutOfStockException $exception) {
            $this->assertSame(0, $exception->available);
        }

        $this->payments()->handleNotification($this->provider()->succeed($initiation->providerReference));
        $this->assertSame(0, $this->stock()->getAvailable($availability));

        // Sold out keeps the row.
        $this->em()->clear();
        $this->assertNotNull($this->em()->find(ProductAvailability::class, $availability->getId()));
    }

    public function testExpiryReleasesStock(): void
    {
        $product = $this->product(1000, 0, slug: 'ticket');
        $availability = (new ProductAvailability())->setQuantity(3);
        $product->addAvailability($availability);
        $this->em()->flush();

        $cart = $this->carts()->create();
        $this->carts()->addProduct($cart, $product, quantity: 2);
        $this->checkout()->checkout($cart, 'card');
        $this->assertSame(1, $this->stock()->getAvailable($availability));

        $cart->setDateUpdated(new \DateTimeImmutable('-3 hours'));
        $this->em()->flush();

        $tester = new CommandTester((new Application(static::$kernel))->find('shop:expire'));
        $this->assertSame(0, $tester->execute(['--older-than' => 'PT2H']));
        $this->assertStringContainsString('1 cart(s) expired', $tester->getDisplay());

        $this->assertSame(CartStatus::Expired, $cart->getStatus());
        $this->assertSame(3, $this->stock()->getAvailable($availability));
        $this->assertSame(1, $this->events()->count(CartExpiredEvent::class));
    }
}
