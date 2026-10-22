<?php

namespace Wexample\SymfonyShop\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Wexample\SymfonyMedia\Entity\MediaAccessGrant;
use Wexample\SymfonyShop\Entity\CartItem;

/**
 * The files of a paid item, one grant each: what the application writes to
 * the buyer, with each grant's link (MediaAccessService::getUrl()).
 */
class DigitalGoodsDeliveredEvent extends Event
{
    /**
     * @param list<MediaAccessGrant> $grants
     */
    public function __construct(
        public readonly CartItem $item,
        public readonly array $grants,
    ) {
    }
}
