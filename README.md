# symfony_shop

Version: 3.0.1

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

## Table of Contents

- [Filling a cart](#filling-a-cart)
- [Checkout](#checkout)
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

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.5
- wexample/symfony-helpers: >=15.0.0
- wexample/symfony-money: >=5.0.0
- wexample/symfony-geo: >=5.0.0
- wexample/symfony-payment: >=3.0.0

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

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
