# Extract cart and catalog from network

Opened: 2026-09-24
Updated: 2026-10-12
Author: agent:archeology

## Closed (2026-10-12)

Backend done (see below). Package renamed from symfony-cart to symfony-shop; tables kept. Dropped by the owner: admin forms for variations and availabilities (#301) — catalogues are filled by each application. Not planned for now: recurring schedules (`Schedule.rule`), the tunnel as a symfony-testing scenario.

## Status (2026-10-04)

Done (phases A and B): Product, ProductVariation, Schedule, ProductAvailability, StockReservation, Cart, CartItem; CartService (owner attach, limits fixed, price resolvers), CartCheckoutService (checkout through symfony-payment, completion, CartPaidEvent and per-type paid handlers, reopen, cancel, expire), StockService (locked reservations, never deletes availabilities), `shop:expire`, sales per product; 11 tests. Left: admin forms for variations and availabilities (#301), recurring schedules, the tunnel scenario as an integration test.

## Read this first — status of this todo

> **This is a proposal for discussion, not an order to code.** It was written by the 2026-09 network archaeology pass. Read it, then discuss it with the owner: every design choice and recommendation below is to be challenged and validated **before** any code is written. Do not start implementing on your own.
>
> - Context: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/index.md.j2` (entry point, order between packages), then `sources.md.j2` (where the legacy code lives: archive repo, branch checkouts, GitLab issues) and the domain page linked below.
> - Pending owner decisions affecting this work are listed in `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/recap.md.j2`, section "Décisions qui t'attendent". Where this todo assumes an answer, treat it as an open question.
> - Safety: `NETWORK/local/network` runs on **production data** (real bookkeeping, real invoices in `var/`, a prod dump in `.wex/mysql/dumps/`) — read its code only, never run anything against it. Anonymize any fixture taken from network (bank exports, FEC, mails contain real names/accounts). Never copy secrets found in its history (Stripe keys, tokens, passwords, private keys).

## Goal

Fill the empty `symfony-shop` package with network's cart. The owner's words: "I had worked the cart well too, I believe."

Phase A is the cart itself:
- anonymous-capable cart with a status lifecycle;
- items that snapshot product prices, with quantity, VAT, comment, variations and schedule;
- a limit on items per product;
- immutability once payment starts;
- billing/shipping address snapshots;
- checkout through `symfony-payment`;
- a `CartPaidEvent` with per-product-type paid handlers;
- expiry of abandoned carts.

Phase B is the catalog it needs:
- products, variations with price actions, schedules, and availability with reservation (#313).

If the owner chooses a separate `symfony-product` package (open question on the knowledge page), move phase B there unchanged.

Knowledge page: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/cart-payment.md.j2`. Read Inventory, "Cart lifecycle", "Payment flow" steps 5–7, Tests & demos, Pitfalls, and "Recommended target design" §4–§6. For the tunnel steps that drive the cart, see `tunnel.md.j2` in the same folder.

Issues, in `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/`:

| Issue | Topic | Status |
|---|---|---|
| `055.md` | anonymous cart before account | done in fos |
| `206.md` | item comment | |
| `135.md` | VAT per item | |
| `241.md` | admin form based on a temporary cart | |
| `290.md` | product sale feature list | |
| `297.md` | several prices; member price | member price todo |
| `301.md` | schedules | admin UI todo |
| `304.md` | sales per product | |
| `305.md` | product administrators | |
| `313.md` | temporary decrement and abandoned carts | todo |
| `319.md` | pending carts vs deploy | |

## Prerequisites

- `symfony-money` with the priced-entity engine. See `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/PACKAGES/PHP/packages/wexample/symfony-money/.wex/journal/todo/extract-from-network.md`.
- `symfony-payment`. See `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/proposed-packages/symfony-payment/todo/extract-from-network.md`.
- `symfony-helpers` for base entity traits.
- `symfony-geo` for `Country`. The `Address` entity is owned by the organization domain (`organization.md.j2`). Reference an address through an interface or mapped superclass, and do not create a second Address entity without checking that page.
- Run `wex ai::design/rules --formatter php-code` first.

## Decisions already implied

- A cart can exist without a user. It is attached later: network creates the cart first, and only creates a *disabled* user at the email step.
- `CartItem` copies the product's `priceRaw`, `priceVat` and `priceOverridden` when the product is set. Later product edits never change existing carts.
- A free-amount item (donation) is a product item whose `priceRaw` is set to the chosen amount, with an optional comment.
- Once the cart is `waiting_payment` or `paid`, items cannot change. network enforced this only for `paid` (`preventEditionIfPaid`).
- Paying produces side effects through handlers selected by product **type/code**, not hard-coded ids. network used ids 10, 11 and 12 with a `productServices[id]` map, and that map silently broke in prod, see #322.
- Invoice generation, mails, membership and Rocket.Chat sync are app listeners, not package code.

## Steps

### Phase A: cart

1. Read the sources, relative to `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/`:
   - Entities: `Entity/Cart.php`, `Entity/CartItem.php`, `Entity/Traits/HasProductTrait.php`, `Entity/Traits/HasSchedule.php`, `Entity/Traits/HasCommentTrait.php`, `Entity/Traits/HasQuantityTrait.php`.
   - Repositories: `Repository/CartRepository.php`, `Repository/CartItemRepository.php`.
   - Services: `Service/Entity/CartEntityService.php` (`addProduct`, `addProductAndRemoveMaxItemsInCart`, `removeAllCartItems`, `removeAllProductIdToCart`, `createCart`, `saveCart`/`persistCart`, `completeCartPayment`, `sendPaymentSuccessNotifications*`), `Service/Entity/CartItemEntityService.php`, `Service/Entity/Product/{AbstractProductEntityService,MembershipRenewalProductEntityEntityService}.php`.
   - Tunnel steps: `Service/Tunnel/Payment/Traits/CartTunnelStepTrait.php` (session cart creation and reset), `Service/Tunnel/Payment/Pay.php` (payment creation and `onPaymentEvent`), `Service/Tunnel/SingleProductTunnelService.php::clearExpiredSession`.
   - Addresses: `Repository/AddressRepository.php::saveCloneInAddressBookAndAssign`, `Entity/Address.php`.
   - Prod version, for comparison only: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/src/Entity/Cart.php` and `src/Service/Entity/CartEntityService.php`.
2. Entities: `AbstractCart` and `AbstractCartItem` as mapped superclasses so the app can extend them (network adds invoice and mailable relations).
   - Cart:
     - status enum `opened|waiting_payment|paid|canceled|expired|error`. Drop `current`, and include every status in the list; network's `getStatusList` missed two.
     - `user` nullable, `dateCreated`, `datePaid`, `payment` (symfony-payment), `addressBilling`, `addressShipping`.
     - implements `PayableInterface`.
     - uses `PricedParentWithVatChildrenTrait`.
   - CartItem:
     - `product` (via `CartProductInterface` or the phase B entity), `quantity`, `comment`, `schedule` nullable.
     - `variations` ManyToMany.
     - `PricedChildTrait`, `PricedWithVatTrait`, `HasPricedQuantityTrait`.
     - variation price actions (`applyProductVariations` in `CartItem.php`).
3. `Service/CartService`:
   - `create(?user)`, `attachUser()`, `addProduct(cart, product, qty=1, ?amount, ?schedule, variations=[])`.
   - `limitProductItems(cart, product, ?schedule, max=1)`. This fixes `addProductAndRemoveMaxItemsInCart`: its no-schedule branch compares `$product` instead of `$schedule`, and it must update qty/amount on the kept item.
   - `removeProduct()`, `clear()`, `recompute()`, plus guards.
   - Note: network's `getOrSaveNewCurrentCart()` uses `$cart` before assigning it. Do not port that.
4. `Service/CartCheckoutService`:
   - `checkout(cart, method)`: set `waiting_payment`, create or reuse the Payment for the cart total, re-init it if the total changed, and complete zero-total carts immediately.
   - On `PaymentSucceededEvent` whose payable is a cart: `completeCart()` sets `paid` and `datePaid`, snapshots the addresses, dispatches `CartPaidEvent`, then calls every `CartItemPaidHandlerInterface` that supports the item's product type.
   - On failure or cancel: back to `opened`.
5. Address snapshot: on paid, freeze the cart addresses and copy them into the user's address book (network's `TYPE_ASSIGNED` / `TYPE_ADDRESS_BOOK` plus `cloneFrom`). Coordinate the entity with the organization/geo extraction.
6. Expiry: add the command `shop:expire --older-than=PT2H`. It moves stale `opened`/`waiting_payment` carts without a succeeded payment to `expired` and releases reservations (phase B). It also dispatches `CartExpiredEvent`, so the app can delete disabled shadow users as network's `clearExpiredSession` did.

### Phase B: catalog (or `symfony-product`)

7. Read, relative to the same `src/`:
   - Entities: `Entity/Product.php`, `Entity/ProductVariation.php`, `Entity/Schedule.php`, `Entity/ProductScheduleAvailability.php`.
   - CRUD: `Service/EntityCrud/{ProductVariation,ProductScheduleAvailability}EntityCrudService.php`.
   - Tunnel: `Service/Tunnel/SingleProduct/{ChooseVariations,ChooseSchedule}.php`, `Service/Tunnel/SingleProduct/Traits/HasProductScheduleAvailability.php`.
   - Migrations: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/migrations/Version2022100*.php`, `Version202210{10,13,15}*.php`, `Version20221107195257.php`.
8. Entities:
   - `AbstractProduct`: title, slug, description nullable, status enum, `type`/`code` replacing ids 10/11/12, `hasShipping`, `PricedSingleTrait`, `PricedWithVatTrait`.
   - `ProductVariation`: name, value, `priceAction` enum `none|price_raw|price_final`, amount. Setting the action must not require the product to be set; network's `setAction()` calls `getProduct()->updatePriceTotal()`.
   - `Schedule`: dateStart, dateEnd, type `fixed`. Leave a hook for recurrence, e.g. #290's "last Monday of each month".
   - `ProductAvailability`, renamed from `ProductScheduleAvailability`: product, schedule nullable, variation nullable, quantity nullable (null means unlimited). Fix the typo in network's trait name `ProductScheduleEvailability…`.
9. Reservation (#313): a `StockReservation` entity (availability, cart item, quantity, expiresAt).
   - Available = quantity − paid − active reservations.
   - Reserve at checkout, confirm on paid, release on expire/cancel.
   - Use a DB lock or a conditional UPDATE to avoid overselling.
   - **Never delete** an availability that reaches 0. network does this in `decreaseAvailabilityQuantityForTunnelSession()`.
10. `Interface/PriceResolverInterface` (default: product price). This is the extension point for #297's member price and customer-group pricing. Implement only the default.
11. Admin CRUD forms for variations and availabilities are missing in network (#301); add them with `symfony-forms` if time allows. Also add a sales-per-product query (#304): paid cart items by product.

## Do not

- Do not port hard-coded product ids, tunnel ids or thanks-step ids.
- Do not port `CartMembershipEntityService` (empty shell), `MembershipService` or `Membership` NotOrm. Those are app membership code.
- Do not generate invoices, PDFs or mails in the package. network's `completeCartPayment` does, but in the new design that becomes listeners on `CartPaidEvent`.
- Do not port `generateInvoiceFromCart()` as-is (it double-counts quantity and drops item VAT). Leave a note for the accounting extraction.
- Do not port the tunnel steps. They stay in the app or in `symfony-tunnels`, calling `CartService` / `CartCheckoutService`.
- Do not store the Stripe intent id on the cart (network's deprecated `Cart.stripeIntentId`).

## Acceptance (tests in the package)

- Pricing through the cart:
  - product 25000 + VAT 2000 → item 30000;
  - quantity 2 → cart 60000;
  - variation `price_final` 200 → item final 200;
  - variation `price_raw` 0 → spectator item 0 + VAT.
  - Port the logic of `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/tests/Unit/Accounting/Pricing/ProductPricingTest.php`.
- Snapshot: changing the product price after adding it leaves the item price unchanged.
- `limitProductItems` keeps exactly one item per product+schedule, including when there is no schedule.
- Adding an item to a `waiting_payment` or `paid` cart throws.
- Checkout with the fake provider from `symfony-payment`:
  - success → cart `paid`, `CartPaidEvent` dispatched once, and the paid handler for the product type called once;
  - replayed success → no second call;
  - zero-total cart → paid without a provider.
- Expiry command moves an old `waiting_payment` cart to `expired` and releases its reservation.
- Reservation: quantity 1, and two carts reserving concurrently → the second is refused. A paid reservation decrements the availability. A 0 left does not delete the row.
- Scenario reference to port as an integration test: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop/tests/Application/Role/Anonymous/Controller/Tunnels/SingleProductTunnelControllerTest.php`: presenter/spectator variations, a schedule, and availability decreasing after payment.
