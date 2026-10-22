<?php

namespace Wexample\SymfonyShop\Enum;

/**
 * What choosing a variation does to the item price.
 */
enum VariationPriceAction: string
{
    /** Nothing: the variation is only descriptive (size, colour). */
    case None = 'none';

    /** Replaces the unit price before VAT; VAT still applies. */
    case PriceRaw = 'price_raw';

    /** Replaces the final price, VAT included. */
    case PriceFinal = 'price_final';
}
