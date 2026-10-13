<?php

namespace Wexample\SymfonyShop\Enum;

enum CartStatus: string
{
    case Opened = 'opened';

    /** The buyer is on the payment page: back to the cart reopens it. */
    case WaitingPayment = 'waiting_payment';

    /** Paid by hand (transfer, cheque, cash): someone has to see the money arrive. */
    case AwaitingValidation = 'awaiting_validation';

    case Paid = 'paid';

    /** Paid and delivered: nothing left to do for it. */
    case Fulfilled = 'fulfilled';

    case Canceled = 'canceled';

    case Expired = 'expired';

    case Error = 'error';

    public function isEditable(): bool
    {
        return self::Opened === $this;
    }

    public function isPaid(): bool
    {
        return in_array($this, [self::Paid, self::Fulfilled], true);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Paid, self::Fulfilled, self::Canceled, self::Expired], true);
    }
}
