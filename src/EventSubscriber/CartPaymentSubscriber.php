<?php

namespace Wexample\SymfonyShop\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyPayment\Event\AbstractPaymentEvent;
use Wexample\SymfonyPayment\Event\PaymentCanceledEvent;
use Wexample\SymfonyPayment\Event\PaymentFailedEvent;
use Wexample\SymfonyPayment\Event\PaymentSucceededEvent;
use Wexample\SymfonyShop\Entity\Cart;
use Wexample\SymfonyShop\Repository\CartRepository;
use Wexample\SymfonyShop\Service\CartCheckoutService;

/**
 * Completes or reopens carts when their payment changes.
 */
class CartPaymentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly CartCheckoutService $checkoutService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentSucceededEvent::class => 'onSucceeded',
            PaymentFailedEvent::class => 'onFailedOrCanceled',
            PaymentCanceledEvent::class => 'onFailedOrCanceled',
        ];
    }

    public function onSucceeded(PaymentSucceededEvent $event): void
    {
        if ($cart = $this->findCart($event)) {
            $this->checkoutService->complete($cart, $event->payment);
        }
    }

    public function onFailedOrCanceled(AbstractPaymentEvent $event): void
    {
        $cart = $this->findCart($event);

        // A payment replaced by another one (amount changed) must not reopen the cart.
        if ($cart && $cart->getPayment() === $event->payment) {
            $this->checkoutService->reopen($cart);
        }
    }

    private function findCart(AbstractPaymentEvent $event): ?Cart
    {
        $payment = $event->payment;

        if (Cart::PAYABLE_TYPE !== $payment->getPayableType() || ! Uuid::isValid((string) $payment->getPayableId())) {
            return null;
        }

        return $this->cartRepository->find(Uuid::fromString($payment->getPayableId()));
    }
}
