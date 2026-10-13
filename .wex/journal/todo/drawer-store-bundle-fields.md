# Store fields from wexStoreBundle (2018) missing from network and symfony-shop

Opened: 2026-10-09
Updated: 2026-10-09
Author: agent:archeology-drawers

## Read this first — status of this todo

> **This is a proposal for discussion, not an order to code.** It comes from the 2026-10 "bottom of the drawers" archaeology pass. Read it, then discuss it with the owner: every design choice below is to be challenged and validated **before** any code is written. Do not start implementing on your own.
>
> - **Before anything else**: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/PACKAGES/PHP/.wex/local/package-agent-intro.md` — where you stand in the suite, what a package must look like, how to work, and how to judge a drawer todo.
> - Context: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/index.md.j2`, `sources.md.j2`, and the drawers page linked below.
> - Safety: `NETWORK/local/network` runs on production data — read its code only, never run anything against it. Anonymize anything taken from it. Never copy secrets found in its history.

Drawers page: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/drawers-outside.md.j2`

## What it is

`wexStoreBundle` (GitLab `wexample/wexStoreBundle`, 11 commits, all 2018-01-11, Doctrine mapped superclasses only, no services) is the 2018 store model. Some of its fields never reached network's Cart/Product nor `symfony-shop`.

## Where

`/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/outside/wexample_wexStoreBundle/Entity/{Product,Cart,CartItem,PaymentHistory}.php` (branches `master` and `data-model` differ only in `composer.json`). Compare with `cart-payment.md.j2`.

## Fields absent today

- Product: `stock` + `stockMinThreshold` (low-stock alert), `provider` (supplier), `priceFactor` (int, default 1), `image`, boolean `status`.
- Cart statuses: ERROR_PAYMENT -1, CANCELED 0, INITIALIZED 1, WAITING_PAYMENT 2, **WAITING_VALIDATION 3** (manual check, e.g. bank transfer), PAID 4, **CLOSED 5** (fulfilled).
- Cart: `shippingTotal`, `vatAmount`, **`intraCommunityVat`** (buyer's EU VAT number), **`shippingDateExpected`**, **`contactEmail` / `contactPhone`** (guest buyer), **`paymentProof`** (uploaded proof file), **`origin`** (sales channel).
- CartItem: per-line discount.
- PaymentHistory: `type` + raw `data` = log of payment-provider events.

## Proposed shape (suggestion)

Consider for `symfony-shop` v1: statuses `awaiting_validation` and `fulfilled`; payment proof for manual payments (with `symfony-payment`'s manual gateway); buyer VAT number and contact on the cart snapshot; a low-stock threshold on availability; a sales-channel field; a raw provider-event log in `symfony-payment`.

## Open questions

1. Which of these does network 2027 actually need (no physical goods today)?
