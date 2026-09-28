<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Notifications\Notifications\QuotationDecidedNotification;
use App\Domain\Quotations\Actions\CustomerDecideQuotationAction;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationLine;
use App\Domain\Settings\TenantSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\PublicQuotationDecisionRequest;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public, sign-gated quotation review for the customer — no login. Reachable only
 * with a valid signature (the `signed` middleware); shows a customer-safe view of
 * the quotation (never internal cost) and lets them accept or reject it.
 */
class PublicQuotationController extends Controller
{
    public function show(Quotation $quotation): Response
    {
        $quotation->load(['customer', 'lines']);

        return Inertia::render('quotations/PublicReview', [
            'quotation' => $this->serialize($quotation),
            'company' => $this->companyName(),
            'actions' => [
                'accept' => URL::signedRoute('tenant.quotations.review.accept', ['quotation' => $quotation->public_id]),
                'reject' => URL::signedRoute('tenant.quotations.review.reject', ['quotation' => $quotation->public_id]),
            ],
        ]);
    }

    public function accept(PublicQuotationDecisionRequest $request, Quotation $quotation, CustomerDecideQuotationAction $action): RedirectResponse
    {
        return $this->decide($request, $quotation, $action, QuotationStatus::Accepted);
    }

    public function reject(PublicQuotationDecisionRequest $request, Quotation $quotation, CustomerDecideQuotationAction $action): RedirectResponse
    {
        return $this->decide($request, $quotation, $action, QuotationStatus::Rejected);
    }

    protected function decide(
        PublicQuotationDecisionRequest $request,
        Quotation $quotation,
        CustomerDecideQuotationAction $action,
        QuotationStatus $decision,
    ): RedirectResponse {
        // Idempotent-friendly: a second submission on an already-decided quote just
        // returns to the (now read-only) review rather than erroring.
        if ($quotation->status !== QuotationStatus::Sent) {
            return redirect()->to($this->reviewUrl($quotation));
        }

        $name = $request->string('name')->toString();

        $action->handle($quotation, $decision, $name, $request->ip(), $request->string('reason')->toString() ?: null);

        $this->notifyOwner($quotation, $decision === QuotationStatus::Accepted, $name);

        return redirect()->to($this->reviewUrl($quotation));
    }

    protected function notifyOwner(Quotation $quotation, bool $accepted, string $name): void
    {
        $owner = $quotation->creator;

        if ($owner === null || ! $owner->isActive()) {
            return;
        }

        Notification::send($owner, new QuotationDecidedNotification(
            $quotation->id, $quotation->number, $quotation->public_id, $accepted, $name,
        ));
    }

    protected function reviewUrl(Quotation $quotation): string
    {
        return URL::signedRoute('tenant.quotations.review', ['quotation' => $quotation->public_id]);
    }

    protected function companyName(): string
    {
        $name = app(TenantSettings::class)->get('general', 'company_name');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return (string) (function_exists('tenant') && tenant() ? tenant('name') : config('app.name'));
    }

    /**
     * Customer-safe payload — never internal cost or internal line names.
     *
     * @return array<string, mixed>
     */
    protected function serialize(Quotation $quotation): array
    {
        return [
            'number' => $quotation->number,
            'status' => $quotation->status->value,
            'currency' => $quotation->currency,
            'issue_date' => $quotation->issue_date->toDateString(),
            'valid_until' => $quotation->valid_until?->toDateString(),
            'notes' => $quotation->notes,
            'terms' => $quotation->terms,
            'customer' => $quotation->customer?->company_name,
            'decided_by_name' => $quotation->decided_by_name,
            'subtotal' => Money::toMajor($quotation->subtotal),
            'discount_total' => Money::toMajor($quotation->discount_total),
            'tax_total' => Money::toMajor($quotation->tax_total),
            'grand_total' => Money::toMajor($quotation->grand_total),
            'lines' => $quotation->lines->map(fn (QuotationLine $l): array => [
                'name' => $l->customer_alias,
                'description' => $l->description,
                'quantity' => $l->quantity,
                'unit' => $l->unit,
                'unit_price' => Money::toMajor($l->unit_price),
                'line_total' => Money::toMajor($l->line_total),
            ])->all(),
        ];
    }
}
