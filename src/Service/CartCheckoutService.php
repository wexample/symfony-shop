<?php

namespace Wexample\SymfonyShop\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Wexample\SymfonyGeo\Interface\PostalAddressInterface;
use Wexample\SymfonyPayment\Class\ManualPaymentGateway;
use Wexample\SymfonyPayment\Entity\Payment;
use Wexample\SymfonyPayment\Enum\PaymentStatus;
use Wexample\SymfonyPayment\Service\PaymentService;
use Wexample\SymfonyRemotePayment\Class\PaymentInitiation;
use Wexample\SymfonyShop\Entity\Cart;
use Wexample\SymfonyShop\Enum\CartStatus;
use Wexample\SymfonyShop\Event\CartExpiredEvent;
use Wexample\SymfonyShop\Event\CartFulfilledEvent;
use Wexample\SymfonyShop\Event\CartPaidEvent;
use Wexample\SymfonyShop\Interface\CartItemPaidHandlerInterface;

/**
 * From a filled cart to a paid one, then a delivered one.
 *
 * checkout() charges the buyer's VAT rates, freezes the cart, holds the stock and
 * starts the payment; the payment events (see CartPaymentSubscriber) then complete
 * the cart or reopen it. A payment made by hand (transfer, cheque, cash) leaves the
 * cart awaiting validation, its stock held for days, until someone confirms the
 * money arrived. fulfill() closes a paid cart once delivered.
 */
class CartCheckoutService
{
    /**
     * @param iterable<CartItemPaidHandlerInterface> $paidHandlers
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CartService $cartService,
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
        if ($cart->getStatus()->isPaid()) {
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

        $this->cartService->applyVatRates($cart);
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

        $initiation = $this->paymentService->initiate($payment, $returnUrl);

        if (ManualPaymentGateway::NAME === $payment->getProvider() && PaymentStatus::Pending === $payment->getStatus()) {
            $cart->setStatus(CartStatus::AwaitingValidation);
            $this->stockService->holdCart($cart);
        }

        return $initiation;
    }

    /**
     * Back to editing, e.g. the buyer returns to the cart from the payment page,
     * or a payment made by hand was canceled.
     */
    public function reopen(Cart $cart): void
    {
        if (! in_array($cart->getStatus(), [CartStatus::WaitingPayment, CartStatus::AwaitingValidation], true)) {
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
        if ($cart->getStatus()->isPaid()) {
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

    /**
     * The paid cart was delivered: nothing left to do for it. Idempotent.
     */
    public function fulfill(
        Cart $cart,
        ?DateTimeImmutable $dateFulfilled = null
    ): bool {
        if (CartStatus::Fulfilled === $cart->getStatus()) {
            return false;
        }

        if (CartStatus::Paid !== $cart->getStatus()) {
            throw new \LogicException('Only a paid cart can be fulfilled.');
        }

        $cart->setStatus(CartStatus::Fulfilled)
            ->setDateFulfilled($dateFulfilled ?? new DateTimeImmutable());
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new CartFulfilledEvent($cart));

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
