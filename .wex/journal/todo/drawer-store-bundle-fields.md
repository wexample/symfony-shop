# Store fields from wexStoreBundle (2018), and invoicing a paid payable without a bridge package

Opened: 2026-10-09
Updated: 2026-10-13
Author: agent:archeology-drawers, design validated with weeger on 2026-10-13

## Read this first — status of this todo

> **The design below was discussed and validated with the owner on 2026-10-13.** It replaces the
> original "consider for v1" proposal. Implement it in the order given; anything not covered here goes
> back to the owner before code.
>
> - **Before anything else**: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/PACKAGES/PHP/.wex/local/package-agent-intro.md`.
> - Context: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/index.md.j2`, `sources.md.j2`, and the drawers page `drawers-outside.md.j2`.
> - Safety: `NETWORK/local/network` runs on production data — read its code only, never run anything against it. Anonymize anything taken from it. Never copy secrets found in its history.

## Origin

`wexStoreBundle` (GitLab `wexample/wexStoreBundle`, 11 commits, all 2018-01-11, Doctrine mapped
superclasses only) is the 2018 store model. Source:
`/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/outside/wexample_wexStoreBundle/Entity/{Product,Cart,CartItem,PaymentHistory}.php`.

Decision: do not port the legacy as-is. Do everything that makes sense today, each field in the package
that owns it.

## Decision 1 — no `symfony-shop-accounting`

`symfony-shop` and `symfony-accounting` stay unaware of each other: neither requires the other, and no
bridge package is created. They meet on contracts placed in the layers both already require,
`symfony-money` and `symfony-payment`.

Accounting invoices **any** payable that is paid, not specifically a cart: on `PaymentSucceededEvent` it
resolves the paid object through `PayableResolverInterface`, reads its lines when it is a
`PricedParentInterface`, reads its buyer when it is a `BuyerIdentifiedInterface`, finds or creates the
`Party`, and calls `InvoiceFactory::createFromLines(origin: 'cart' | payable type)`. On
`PaymentRefundedEvent` it issues a credit note. The same path then invoices subscriptions, registrations,
donations…

This supersedes the earlier note in `symfony-accounting/.wex/journal/done/extract-from-network-invoices.md`
("let the app listen to `CartPaidEvent`"): the glue moves from each app into accounting itself.

Guard: automatic invoicing only runs when configured (a default ledger, or an explicit setting), so an
app holding accounting and non-invoiceable payments gets no stray invoices.

## Decision 2 — VAT is decided by a service of `symfony-money`

`symfony-money` ships `VatRateResolverInterface` and a default implementation returning the product's
own rate (`priceVat`). The shop computes its displayed prices through it. `symfony-accounting` aliases
the interface to its own resolver: buyer country + VAT number → VIES check → reverse charge (0 %).
Plain service alias, no `interface_exists()`.

The verdict already exists: `JurisdictionInterface::resolveVatKind(VatContext)` returns `Domestic`,
`IntraEuServices`, `ReverseCharge`, `Oss`… The resolver only builds the `VatContext` and maps the kind
to a rate.

What it lacks is the VIES check: `isVatNumberValid()` checks format and checksum only
(`AbstractJurisdiction`, `FrIdentityHelper`, `BeIdentityHelper`), nothing in the suite calls VIES. The
owner creates `symfony-remote-vies` (on the model of `symfony-remote-ecb`); `symfony-accounting` requires
it and treats a buyer whose number VIES rejects as a consumer.

## Where each field goes

### `symfony-shop`

- `CartStatus`: add `AwaitingValidation` (manual check, e.g. bank transfer) and `Fulfilled`. Revisit
  `isEditable()` / `isClosed()`.
- `Cart` buyer snapshot: phone (email already exists as `customerEmail`), company name, VAT number. Fixed
  at checkout like the addresses. `Cart` implements `BuyerIdentifiedInterface`.
- `Cart`: sales channel (`origin`), expected shipping date.
- Shipping cost: a fee line rather than a `shippingTotal` field, so the invoice picks it up like any line.
- `CartItem`: per-line discount, with `HasPriceDiscountTrait` of `symfony-money`. Implements
  `AccountNumberedInterface` (from `Product.accountNumber`).
- `ProductAvailability`: low-stock threshold next to `quantity`.

### `symfony-payment`

- `BuyerIdentifiedInterface`: name, company name, email, phone, VAT number, billing address.
- `PaymentEvent`: raw log of provider events (`type` + `data`), the legacy `PaymentHistory`.
- Payment proof for the manual gateway: an uploaded file stored as a private `Media` of `symfony-media`
  (new requirement of `symfony-payment`), in a media library, readable by the buyer and the admin only.
  Not `symfony-remote-pdf-factory`: that one produces documents, a proof is received.

### `symfony-money`

- `VatRateResolverInterface` + default resolver.
- `AccountNumberedInterface`: the revenue account of a line.

### `symfony-accounting`

- Subscriber: paid payable → invoice, refund → credit note, behind the guard above.
- Buyer → `Party` (find by VAT number / email, else create).
- VAT resolver alias (decision 2), with the VIES check through `symfony-remote-vies`.
- Reading the payment proof with `symfony-remote-document-reader` (already required): amount,
  reference, date, compared with the expected payment to help the manual validation. Payment stays free
  of the reader.

### Dropped

- `vatAmount`: computed (`calcVatLines()`), never stored.
- Product `provider`: a supplier `Party`, purchasing side, out of the shop.
- `priceFactor`: unclear meaning, nothing asks for it.
- `image`, `status`: already covered (`symfony-media`, `ProductStatus`).

## Order

1. `symfony-shop` fields — no new dependency.
2. `symfony-money` and `symfony-payment` contracts, `PaymentEvent`, payment proof.
3. `symfony-accounting` subscriber: paid payable → invoice.
4. VAT resolver and its accounting alias, on `ViesClient::check()` of `symfony-remote-vies` (shipped 2026-10-13; `ViesException` = no verdict, not a refusal).
5. Reading the payment proof in accounting.

Each step ships with its tests and its knowledge pages updated.

## Diagram

A Mermaid rendering of this structure was drawn during the discussion (dependencies, payment sequence,
VAT resolution, field mapping). It was only a scratch file; redraw it into
`.wex/knowledge` of the packages concerned once the code exists.
