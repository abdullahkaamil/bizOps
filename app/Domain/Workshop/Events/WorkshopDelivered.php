<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WorkshopDelivered
{
    use Dispatchable;

    public function __construct(public int $ticketId, public int $actorId) {}
}
