<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Enums;

enum EmailStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
}
