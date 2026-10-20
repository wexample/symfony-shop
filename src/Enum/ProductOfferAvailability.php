<?php

namespace Wexample\SymfonyShop\Enum;

/**
 * How much of an offer is left to buy, as a visitor is told it.
 */
enum ProductOfferAvailability: string
{
    case Available = 'available';

    /** At or under the low-stock threshold of its availability. */
    case Limited = 'limited';

    case SoldOut = 'sold_out';
}
