<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Actions\Concerns;

use App\Domain\Workshop\Enums\WorkshopStatus;
use App\Domain\Workshop\Exceptions\InvalidWorkshopTransition;
use App\Domain\Workshop\Models\WorkshopStatusHistory;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Shared machinery for workshop transitions: validate current state, apply the
 * change atomically, append an immutable history row and an activity-log entry,
 * and run an after-commit hook.
 */
trait RecordsWorkshopTransition
{
    protected function guardStatus(WorkshopTicket $ticket, WorkshopStatus ...$allowed): void
    {
        if (! in_array($ticket->status, $allowed, true)) {
            throw InvalidWorkshopTransition::wrongStatus($ticket->status, array_values($allowed));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     * @param  callable():void|null  $afterCommit
     */
    protected function transition(
        WorkshopTicket $ticket,
        User $actor,
        WorkshopStatus $to,
        array $attributes = [],
        array $metadata = [],
        ?callable $afterCommit = null,
    ): WorkshopTicket {
        $from = $ticket->status;

        DB::transaction(function () use ($ticket, $actor, $from, $to, $attributes, $metadata, $afterCommit): void {
            $ticket->fill($attributes);
            $ticket->status = $to;
            $ticket->save();

            WorkshopStatusHistory::create([
                'workshop_ticket_id' => $ticket->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_id' => $actor->id,
                'metadata' => $metadata === [] ? null : $metadata,
                'created_at' => now(),
            ]);

            activity('workshop')->performedOn($ticket)->causedBy($actor)
                ->withProperties(['from' => $from->value, 'to' => $to->value])
                ->event($to->value)->log("workshop.{$from->value}_to_{$to->value}");

            if ($afterCommit !== null) {
                DB::afterCommit($afterCommit);
            }
        });

        return $ticket->refresh();
    }
}
