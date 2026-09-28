<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Exceptions\InvalidQuotationTransition;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationStatusHistory;
use Illuminate\Support\Facades\DB;

/**
 * A customer's own accept/reject decision made through the public signed link —
 * no logged-in user. Records who decided (name + IP) on the quotation and an
 * actor-less history row, so the acceptance is auditable. Only a "sent" quotation
 * can be decided.
 */
class CustomerDecideQuotationAction
{
    public function handle(Quotation $quotation, QuotationStatus $decision, string $name, ?string $ip, ?string $reason = null): Quotation
    {
        if ($quotation->status !== QuotationStatus::Sent) {
            throw InvalidQuotationTransition::wrongStatus($quotation->status, [QuotationStatus::Sent]);
        }

        if (! in_array($decision, [QuotationStatus::Accepted, QuotationStatus::Rejected], true)) {
            throw InvalidQuotationTransition::wrongStatus($decision, [QuotationStatus::Accepted, QuotationStatus::Rejected]);
        }

        $from = $quotation->status;

        DB::transaction(function () use ($quotation, $decision, $name, $ip, $reason, $from): void {
            $quotation->status = $decision;
            $quotation->decided_by_name = $name;
            $quotation->decided_ip = $ip;

            if ($decision === QuotationStatus::Accepted) {
                $quotation->accepted_at = now();
            } else {
                $quotation->rejected_at = now();
            }

            $quotation->save();

            QuotationStatusHistory::create([
                'quotation_id' => $quotation->id,
                'from_status' => $from,
                'to_status' => $decision,
                'actor_id' => null, // decided by the customer, not a tenant user
                'reason' => $reason,
                'created_at' => now(),
            ]);

            activity('quotation')
                ->performedOn($quotation)
                ->withProperties(['from' => $from->value, 'to' => $decision->value, 'customer' => $name])
                ->event($decision->value)
                ->log("quotation.customer_{$decision->value}");
        });

        return $quotation->refresh();
    }
}
