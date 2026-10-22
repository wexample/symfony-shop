<?php

namespace Wexample\SymfonyShop\Enum;

enum ProductStatus: string
{
    case Draft = 'draft';

    case Active = 'active';

    case Archived = 'archived';
}
