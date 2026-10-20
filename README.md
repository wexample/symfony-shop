# symfony_shop

Version: 4.0.3

## Filling a cart

```php
$cart = $cartService->create();                                   // anonymous
$cartService->addProduct($cart, $ticket, quantity: 2, schedule: $session, variations: [$presenter]);
$cartService->addProduct($cart, $donation, amount: 4200);         // free-amount product
$cartService->attachOwner($cart, (string) $user->getId(), $user->getEmail());
```

Items copy the product's price when added: editing a product never changes existing carts. Variations replace the raw price (VAT still applies) or the final price. An item takes its own discount (`setPriceDiscount()`, money or percent), the cart one more on the whole. A product's `maxPerCart` keeps only the last items; `PriceResolverInterface` services set member or group prices.

Shipping costs are a line like any other: a product of its own (type `shipping`) added to the cart, so that the invoice carries it without a field of its own.

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

## Table of Contents

- [Filling a cart](#filling-a-cart)
- [The buyer](#the-buyer)
- [Checkout](#checkout)
- [Low stock](#low-stock)
- [Digital products](#digital-products)
- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Architecture

src/Entity/Cart.php and src/Entity/CartItem.php use the symfony-money priced traits; an item keeps the product's price in `priceBase` and derives its effective prices from its variations. src/Entity/ProductAvailability.php rows are never deleted: what is left is the quantity minus confirmed and unexpired reservations (src/Entity/StockReservation.php). src/Service/StockService.php reserves inside a transaction with the availability row locked, and checks everything before persisting anything, so a refused reservation leaves the entity manager usable.

src/EventSubscriber/CartPaymentSubscriber.php is the only link with payments: the cart is a `PayableInterface`, the payment knows it by type and id, and a payment replaced by another never reopens the cart.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

## Dependencies

- php: >=8.5
- wexample/symfony-helpers: >=17.0.0
- wexample/symfony-money: >=7.0.0
- wexample/symfony-geo: >=7.0.0
- wexample/symfony-payment: >=4.0.0
- wexample/symfony-media: >=3.0.0

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

Built and maintained by [Romain Weeger](https://weeger.io).

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
