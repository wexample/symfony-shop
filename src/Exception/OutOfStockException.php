<?php

namespace Wexample\SymfonyShop\Exception;

use Wexample\SymfonyShop\Entity\ProductAvailability;

class OutOfStockException extends \RuntimeException
{
    public function __construct(
        public readonly ProductAvailability $availability,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(sprintf(
            'Only %d unit(s) of "%s" left, %d requested.',
            $available,
            $availability->getProduct()->getTitle(),
            $requested
        ));
    }
}
