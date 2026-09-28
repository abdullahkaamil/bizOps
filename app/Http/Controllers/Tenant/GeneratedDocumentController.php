<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Documents\Models\GeneratedDocument;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves generated documents. Reached only through a temporary signed URL
 * (validated by the `signed` middleware); this handler additionally enforces the
 * policy and streams the file from the DB-recorded path — never from request
 * input. Tenant context is guaranteed by the subdomain tenancy middleware and by
 * the document row living in the tenant database.
 */
class GeneratedDocumentController extends Controller
{
    public function download(GeneratedDocument $document): StreamedResponse
    {
        $this->authorize('download', $document);

        $filename = ($document->number ?? $document->document_type->value).'.pdf';

        return Storage::disk($document->disk)->download($document->path, $filename, [
            'Content-Type' => $document->mime_type,
        ]);
    }
}
