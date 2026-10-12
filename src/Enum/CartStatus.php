<?php

namespace Wexample\SymfonyShop\Enum;

enum CartStatus: string
{
    /** Being filled. The only status in which items can change. */
    case Opened = 'opened';

    /** Checked out, waiting for the payment. Items are frozen. */
    case WaitingPayment = 'waiting_payment';

    case Paid = 'paid';

    case Canceled = 'canceled';

    /** Abandoned: left opened or unpaid too long. */
    case Expired = 'expired';

    case Error = 'error';

    public function isEditable(): bool
    {
        return self::Opened === $this;
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Paid, self::Canceled, self::Expired], true);
    }
}
