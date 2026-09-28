<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Quotations\Actions\AcceptQuotationAction;
use App\Domain\Quotations\Actions\CancelQuotationAction;
use App\Domain\Quotations\Actions\DuplicateQuotationAction;
use App\Domain\Quotations\Actions\ExpireQuotationAction;
use App\Domain\Quotations\Actions\RejectQuotationAction;
use App\Domain\Quotations\Actions\SendQuotationAction;
use App\Domain\Quotations\Models\Quotation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RejectQuotationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuotationTransitionController extends Controller
{
    public function send(Request $request, Quotation $quotation, SendQuotationAction $action): RedirectResponse
    {
        $this->authorize('send', $quotation);
        $action->handle($quotation, $request->user());

        return $this->done(__('Quotation sent.'));
    }

    public function accept(Request $request, Quotation $quotation, AcceptQuotationAction $action): RedirectResponse
    {
        $this->authorize('decide', $quotation);
        $action->handle($quotation, $request->user());

        return $this->done(__('Quotation accepted.'));
    }

    public function reject(RejectQuotationRequest $request, Quotation $quotation, RejectQuotationAction $action): RedirectResponse
    {
        $this->authorize('decide', $quotation);
        $action->handle($quotation, $request->user(), $request->validated('reason'));

        return $this->done(__('Quotation rejected.'));
    }

    public function cancel(Request $request, Quotation $quotation, CancelQuotationAction $action): RedirectResponse
    {
        $this->authorize('cancel', $quotation);
        $action->handle($quotation, $request->user(), $request->string('reason')->toString() ?: null);

        return $this->done(__('Quotation cancelled.'));
    }

    public function expire(Request $request, Quotation $quotation, ExpireQuotationAction $action): RedirectResponse
    {
        $this->authorize('decide', $quotation);
        $action->handle($quotation, $request->user());

        return $this->done(__('Quotation expired.'));
    }

    public function duplicate(Request $request, Quotation $quotation, DuplicateQuotationAction $action): RedirectResponse
    {
        $this->authorize('create', Quotation::class);
        $copy = $action->handle($quotation, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation duplicated.')]);

        return to_route('tenant.quotations.show', $copy);
    }

    protected function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
