## Filling a cart

```php
$cart = $cartService->create();                                   // anonymous
$cartService->addProduct($cart, $ticket, quantity: 2, schedule: $session, variations: [$presenter]);
$cartService->addProduct($cart, $donation, amount: 4200);         // free-amount product
$cartService->attachOwner($cart, (string) $user->getId(), $user->getEmail());
```

Items copy the product's price when added: editing a product never changes existing carts. Variations replace the raw price (VAT still applies) or the final price. A product's `maxPerCart` keeps only the last items; `PriceResolverInterface` services set member or group prices.

## Checkout

```php
$initiation = $checkoutService->checkout($cart, 'card', $billingAddress);   // client secret for the payment form
```

Checkout freezes the cart (`waiting_payment`), holds the stock of limited availabilities, and starts the payment — reusing an open one, replacing it when the total changed, completing a free cart at once. The payment's events then complete the cart (`paid`, `CartPaidEvent`, then each `CartItemPaidHandlerInterface` supporting the product type) or reopen it.

`bin/console shop:expire --older-than=PT2H` expires abandoned carts and gives their stock back (`CartExpiredEvent`). `CartItemRepository::sumPaidSales()` gives sales per product.
