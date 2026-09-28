<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Console;

use App\Domain\Quotations\Actions\ExpireQuotationAction;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Models\User;
use App\Support\Tenancy\ActiveTenants;
use Illuminate\Console\Command;

/**
 * Expires sent quotations whose validity has elapsed, across all active tenants.
 * Scheduled daily with an overlap lock (routes/console.php). Tenant iteration is
 * safe and isolated via ActiveTenants.
 */
class ExpireQuotations extends Command
{
    protected $signature = 'quotations:expire';

    protected $description = 'Expire sent quotations past their validity date, across all active tenants';

    public function handle(ExpireQuotationAction $action, ActiveTenants $tenants): int
    {
        $tenants->each(function () use ($action): void {
            $system = User::query()->orderBy('id')->first();

            Quotation::query()
                ->where('status', QuotationStatus::Sent->value)
                ->whereNotNull('valid_until')
                ->whereDate('valid_until', '<', now()->toDateString())
                ->get()
                ->each(function (Quotation $quotation) use ($action, $system): void {
                    if ($system !== null) {
                        $action->handle($quotation, $system);
                    }
                });
        });

        return self::SUCCESS;
    }
}
