<?php

declare(strict_types=1);

namespace App\Domain\Documents\Policies;

use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Enums\Permission;
use App\Models\User;

/**
 * Authorizes document downloads. Internal staff need the permission that governs
 * the document's source module; external representatives may only ever download
 * customer-facing documents that belong to their own customer.
 */
class GeneratedDocumentPolicy
{
    public function download(User $user, GeneratedDocument $document): bool
    {
        if ($user->isExternal()) {
            return $document->document_type->isCustomerFacing()
                && $this->belongsToUsersCustomer($user, $document);
        }

        return $user->can($this->permissionFor($document->document_type)->value);
    }

    public function view(User $user, GeneratedDocument $document): bool
    {
        return $this->download($user, $document);
    }

    protected function permissionFor(DocumentType $type): Permission
    {
        return match ($type) {
            DocumentType::JobServiceReport => Permission::ViewJobs,
            DocumentType::WorkshopCompletionReport,
            DocumentType::WorkshopDeliveryReport => Permission::ViewWorkshop,
            DocumentType::Quotation => Permission::ViewQuotations,
            DocumentType::CustomerExport => Permission::ViewCustomers,
        };
    }

    protected function belongsToUsersCustomer(User $user, GeneratedDocument $document): bool
    {
        if ($user->customer_id === null) {
            return false;
        }

        $related = $document->related;

        return $related !== null
            && isset($related->customer_id)
            && $related->customer_id === $user->customer_id;
    }
}
