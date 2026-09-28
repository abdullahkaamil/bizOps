<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions\Concerns;

use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Exceptions\InvalidQuotationTransition;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait RecordsQuotationTransition
{
    protected function guardStatus(Quotation $quotation, QuotationStatus ...$allowed): void
    {
        if (! in_array($quotation->status, $allowed, true)) {
            throw InvalidQuotationTransition::wrongStatus($quotation->status, array_values($allowed));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  callable():void|null  $afterCommit
     */
    protected function transition(
        Quotation $quotation,
        User $actor,
        QuotationStatus $to,
        array $attributes = [],
        ?string $reason = null,
        ?callable $afterCommit = null,
    ): Quotation {
        $from = $quotation->status;

        DB::transaction(function () use ($quotation, $actor, $from, $to, $attributes, $reason, $afterCommit): void {
            $quotation->fill($attributes);
            $quotation->status = $to;
            $quotation->save();

            QuotationStatusHistory::create([
                'quotation_id' => $quotation->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            activity('quotation')->performedOn($quotation)->causedBy($actor)
                ->withProperties(['from' => $from->value, 'to' => $to->value])
                ->event($to->value)->log("quotation.{$from->value}_to_{$to->value}");

            if ($afterCommit !== null) {
                DB::afterCommit($afterCommit);
            }
        });

        return $quotation->refresh();
    }
}
