<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

enum PriceType: string
{
    case Sale = 'sale';
    case Purchase = 'purchase';
}
