## Filling a cart

```php
$cart = $cartService->create();                                   // anonymous
$cartService->addProduct($cart, $ticket, quantity: 2, schedule: $session, variations: [$presenter]);
$cartService->addProduct($cart, $donation, amount: 4200);         // free-amount product
$cartService->attachOwner($cart, (string) $user->getId(), $user->getEmail());
```

Items copy the product's price when added: editing a product never changes existing carts. Variations replace the raw price (VAT still applies) or the final price. An item takes its own discount (`setPriceDiscount()`, money or percent), the cart one more on the whole. A product's `maxPerCart` keeps only the last items; `PriceResolverInterface` services set member or group prices.

Shipping costs are a line like any other: a product of its own (type `shipping`) added to the cart, so that the invoice carries it without a field of its own.

## What a product offers

```php
foreach ($productOfferService->getOffers($ticket) as $offer) {
    $offer->schedule;       // ?Schedule, still to come
    $offer->variation;      // ?ProductVariation
    $offer->priceFinal;     // VAT included, minor units; null for a free amount
    $offer->remaining;      // null when nothing limits it
    $offer->availability;   // ProductOfferAvailability: available, limited, sold_out
}
```

What a visitor can buy before having a cart, the same wherever it is shown — a product page, a feed, the search engines (symfony-shop-ds describes a product's page with them): one offer per schedule still to come and per variation changing something — its price or its own stock —, plus the product's own offer when it can be bought without such a variation. Its price is computed by a cart item never stored, so it is what a cart charges; it is the catalogue price, a `PriceResolverInterface` deciding for a cart, which a visitor does not have. An offer is `limited` at or under its availability's `lowStockThreshold`. `getRemaining()`, `getSchedules()` and `isSoldOut()` read the same stock for one choice.

## The buyer

```php
$cart->setCustomerName('Ada Lovelace')->setCustomerEmail('ada@example.com')->setCustomerPhone('+32 2 000 00 00')
    ->setCustomerCompanyName('Analytical SA')->setCustomerVatNumber('BE0123456789')
    ->setOrigin('phone')                                             // sales channel, free to the host
    ->setDateShippingExpected(new DateTimeImmutable('2026-11-02'));
```

The cart is a `BuyerIdentifiedInterface` of `symfony-payment`: whoever invoices its payment (symfony-accounting) reads the buyer from it, billing address included, without knowing the cart.

The VAT charged depends on the buyer. `CartService::applyVatRates($cart)` asks money's `VatRateResolverInterface` the rate of each line from the billing country and the VAT number; checkout calls it. By default every buyer pays the product's rate; with `symfony-accounting`, a business from another EU country whose number VIES confirms pays none. An item keeps the product's rate as `priceVatDefault`; an overridden final price, which includes it, is reduced to its base when another rate applies.

## Checkout

```php
$initiation = $checkoutService->checkout($cart, 'card', $billingAddress);   // client secret for the payment form
```

Checkout charges the buyer's VAT rates, freezes the cart (`waiting_payment`), holds the stock of limited availabilities, and starts the payment — reusing an open one, replacing it when the total changed, completing a free cart at once. The payment's events then complete the cart (`paid`, `CartPaidEvent`, then each `CartItemPaidHandlerInterface` supporting the product type) or reopen it.

A payment made by hand — `transfer`, `cheque`, `cash`, through payment's manual gateway — leaves the cart `awaiting_validation`: the buyer cannot reopen it, its stock stays held for a week rather than half an hour, and the buyer may send a proof (`PaymentService::attachProof()`). Confirming the money (`PaymentService::confirmManualPayment()`) pays the cart as any payment would; canceling it reopens the cart.

```php
$checkoutService->fulfill($cart);   // paid → fulfilled, CartFulfilledEvent
```

A paid cart is `fulfilled` once delivered. `CartStatus::isPaid()` holds for both.

`bin/console shop:expire --older-than=PT2H` expires abandoned carts and gives their stock back (`CartExpiredEvent`); a cart awaiting validation is left alone. `CartItemRepository::sumPaidSales()` gives sales per product, delivered ones included.

## Low stock

An availability with a `lowStockThreshold` raises `LowStockEvent` (the availability, the units left) when a sale brings it down to the threshold — once, by the sale that crosses it.

## Digital products

A product of type `digital` (`Product::TYPE_DIGITAL`) sells files: media of `symfony-media`, attached with `addFile()`, which makes them restricted — the library's readers no longer open them. Once the cart is paid, `DigitalDeliveryHandler` gives one access grant per file and dispatches `DigitalGoodsDeliveredEvent` with them; whoever writes to the buyer sends each grant's link (`MediaAccessService::getUrl()`), signed and counted by the library. `getGrants($item)` finds them again for an order page.

```yaml
wexample_symfony_shop:
  digital:
    download_lifetime_days: 30   # 0: no end
    download_max_uses: 5         # 0: no limit
```

A product overrides both in its metadata (`download_lifetime_days`, `download_max_uses`).
