# Digital product delivery after payment (responsite, 2025)

Opened: 2026-10-09
Updated: 2026-10-12
Author: agent:archeology-drawers

## Fait le 2026-10-12 (agent:main, avec le propriétaire)

- symfony-media : média restreint (`Media::$restricted`, hors des listes et refusé aux simples lecteurs) ; `MediaAccessGrant` (expiration, nombre d'utilisations, origine) ; `MediaAccessService` (`grant()`, `getUrl()` signée par l'`UriSigner` de Symfony, `consume()` sous verrou) ; route `/_media/grant/{id}` (404 si signature fausse ou date passée, 410 une fois épuisé).
- symfony-shop : `Product::TYPE_DIGITAL`, fichiers du produit (`shop_product_file`), `DigitalDeliveryHandler` (un accès par fichier, durée et nombre réglables en config ou dans les métadonnées du produit), `DigitalGoodsDeliveredEvent`.
- Le mail au client et les liens dans « mes commandes » : `symfony-shop-ds/.wex/journal/todo/digital-delivery-mail-and-orders.md`.
- Écarté : le prix lu dans le JSON du client et le « TODO » de jeton de l'original (responsite) n'ont pas d'équivalent, le prix vient du produit et le lien est signé.

## Read this first — status of this todo

> **This is a proposal for discussion, not an order to code.** It comes from the 2026-10 "bottom of the drawers" archaeology pass. Read it, then discuss it with the owner: every design choice below is to be challenged and validated **before** any code is written. Do not start implementing on your own.
>
> - Context: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/index.md.j2`, `sources.md.j2`, and the drawers page linked below.
> - Safety: `NETWORK/local/network` runs on production data — read its code only, never run anything against it. Anonymize anything taken from it. Never copy secrets found in its history.

Drawers page: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/drawers-outside.md.j2`

## What it is

The plain-PHP site builder `responsite` (GitLab `responsite/responsite`) gained in January 2025 a small "sell a PDF" flow: Stripe Checkout → paid check → one-time download. Neither network nor the new packages deliver digital goods.

## Where

`/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/outside/responsite_responsite/site/default/section/products/*` (commits `475c8b4` → `e190861`, 2025-01-05): `create-checkout-session.php` (Checkout Session with `metadata.product_id`), `download-success.php` (retrieve session, require `payment_status === 'paid'`, map product id → private file, put a sha256 one-time token in the PHP session), `download.php` (check + delete token, stream the file with no-cache headers), `secure-download.php` (expiry check; token check left as TODO). `core/config/stripe.php` reads env vars only.

## Bugs not to port

The price is taken from the client JSON (`$data['price']`): the buyer can set it. The token check of `secure-download.php` is missing.

## Proposed shape (suggestion)

In `symfony-shop`: a product kind `digital` with attached private file(s) (storage via `symfony-media` / `symfony-file`), a paid-item handler that issues a **signed, expiring, limited-use download link** (Symfony `UriSigner` + a counter), mailed and listed in the buyer's orders. Price always from the server-side product. Payment through `symfony-payment` (Checkout Session would be a Stripe gateway option in `symfony-remote-payment-stripe`).

## Open questions

1. Does network 2027 need to sell files (guides, templates), or is this for other apps only?
2. Download limits: count, expiry, or both?
