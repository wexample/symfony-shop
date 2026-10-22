<?php

namespace Wexample\SymfonyShop\Enum;

enum ReservationStatus: string
{
    /** Held for a cart being paid, until it expires. */
    case Active = 'active';

    /** The cart was paid: the stock is sold. */
    case Confirmed = 'confirmed';

    /** Given back: cart canceled, expired or payment failed. */
    case Released = 'released';
}
