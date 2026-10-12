<?php

namespace Wexample\SymfonyShop\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Wexample\SymfonyShop\Entity\Cart;
use Wexample\SymfonyShop\Enum\CartStatus;
use Wexample\SymfonyShop\Event\CartExpiredEvent;
use Wexample\SymfonyShop\Event\CartPaidEvent;
use Wexample\SymfonyShop\Interface\CartItemPaidHandlerInterface;
use Wexample\SymfonyGeo\Interface\PostalAddressInterface;
use Wexample\SymfonyPayment\Entity\Payment;
use Wexample\SymfonyPayment\Service\PaymentService;
use Wexample\SymfonyRemotePayment\Class\PaymentInitiation;

/**
 * From a filled cart to a paid one.
 *
 * checkout() freezes the cart, holds the stock and starts the payment; the payment
 * events (see CartPaymentSubscriber) then complete the cart or reopen it.
 */
class CartCheckoutService
{
    /**
     * @param iterable<CartItemPaidHandlerInterface> $paidHandlers
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentService $paymentService,
        private readonly StockService $stockService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly iterable $paidHandlers = [],
    ) {
    }

    /**
     * @return PaymentInitiation|null What the browser needs to pay; null when nothing
     *                                is left to pay (free cart) or the payment was already sent.
     */
    public function checkout(
        Cart $cart,
        string $method,
        ?PostalAddressInterface $billingAddress = null,
        ?PostalAddressInterface $shippingAddress = null,
        ?string $returnUrl = null,
    ): ?PaymentInitiation {
        if (CartStatus::Paid === $cart->getStatus()) {
            throw new \LogicException('This cart is already paid.');
        }

        if ($cart->isEmpty()) {
            throw new \LogicException('An empty cart cannot be checked out.');
        }

        if ($billingAddress) {
            $cart->setBillingAddress($billingAddress);
        }

        if ($shippingAddress) {
            $cart->setShippingAddress($shippingAddress);
        }

        $cart->updatePriceTotal();
        $this->stockService->reserveCart($cart);
        $cart->setStatus(CartStatus::WaitingPayment);

        $payment = $this->paymentService->getOrCreateForPayable(
            $cart,
            $method,
            $cart->getCustomerEmail(),
            $cart->getOwnerReference()
        );
        $cart->setPayment($payment);
        $this->entityManager->flush();

        return $this->paymentService->initiate($payment, $returnUrl);
    }

    /**
     * Back to editing, e.g. the buyer returns to the cart from the payment page.
     */
    public function reopen(Cart $cart): void
    {
        if (CartStatus::WaitingPayment !== $cart->getStatus()) {
            return;
        }

        $cart->setStatus(CartStatus::Opened);
        $this->stockService->releaseCart($cart, flush: false);
        $this->entityManager->flush();
    }

    /**
     * Called when the cart's payment succeeded. Idempotent.
     */
    public function complete(
        Cart $cart,
        ?Payment $payment = null
    ): bool {
        if (CartStatus::Paid === $cart->getStatus()) {
            return false;
        }

        $cart->setStatus(CartStatus::Paid)
            ->setDatePaid($payment?->getDatePaid() ?? new DateTimeImmutable());

        if ($payment) {
            $cart->setPayment($payment);
        }

        $this->stockService->confirmCart($cart);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new CartPaidEvent($cart));

        foreach ($cart->getItems() as $item) {
            foreach ($this->paidHandlers as $handler) {
                if ($handler->supports($item->getProduct()->getType())) {
                    $handler->handle($item);
                }
            }
        }

        return true;
    }

    public function cancel(Cart $cart): void
    {
        if ($cart->getStatus()->isClosed()) {
            return;
        }

        $cart->setStatus(CartStatus::Canceled);
        $this->stockService->releaseCart($cart, flush: false);
        $this->entityManager->flush();
    }

    public function expire(Cart $cart): bool
    {
        if (! in_array($cart->getStatus(), [CartStatus::Opened, CartStatus::WaitingPayment], true)
            || $cart->getPayment()?->getStatus()->isPaid()) {
            return false;
        }

        $cart->setStatus(CartStatus::Expired);
        $this->stockService->releaseCart($cart, flush: false);
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new CartExpiredEvent($cart));

        return true;
    }
}
