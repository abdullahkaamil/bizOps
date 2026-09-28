<?php

declare(strict_types=1);

namespace App\Domain\Documents\Generators;

use App\Domain\Documents\Contracts\DocumentGenerator;
use App\Domain\Documents\Contracts\PdfRenderer;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Documents\Support\DocumentContext;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Shared pipeline for PDF generators: render a Blade template to HTML, convert it
 * to PDF, store it privately under a tenant-prefixed path, and record the
 * GeneratedDocument (with checksum, size, metadata). Subclasses only describe
 * their content.
 */
abstract class AbstractDocumentGenerator implements DocumentGenerator
{
    public function __construct(protected PdfRenderer $renderer) {}

    abstract public function type(): DocumentType;

    abstract protected function view(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function viewData(DocumentContext $context): array;

    protected function number(DocumentContext $context): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadata(DocumentContext $context): array
    {
        return [];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function paper(): array
    {
        return ['a4', 'portrait'];
    }

    /**
     * Render the document to HTML (exposed for testing template concerns such as
     * required fields, pagination, and missing optional assets).
     */
    public function renderHtml(DocumentContext $context): string
    {
        return View::make($this->view(), [
            ...$this->viewData($context),
            'branding' => $this->branding($context),
            'settings' => $context->settings,
        ])->render();
    }

    public function generate(DocumentContext $context): GeneratedDocument
    {
        $html = $this->renderHtml($context);
        [$paper, $orientation] = $this->paper();
        $pdf = $this->renderer->render($html, $paper, $orientation);

        $disk = 'local';
        // Tenant-prefixed private path; the file name is never taken from input.
        $path = 'documents/'.tenant('id').'/'.$this->type()->value.'/'.Str::uuid()->toString().'.pdf';
        Storage::disk($disk)->put($path, $pdf);

        return GeneratedDocument::create([
            'document_type' => $this->type(),
            'related_type' => $context->related::class,
            'related_id' => $context->related->getKey(),
            'number' => $this->number($context),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($pdf),
            'checksum' => hash('sha256', $pdf),
            'generated_by' => $context->actor?->id,
            'generated_at' => now(),
            'metadata' => $this->metadata($context) ?: null,
        ]);
    }

    /**
     * Tenant branding + legal details for the document header.
     *
     * @return array<string, mixed>
     */
    protected function branding(DocumentContext $context): array
    {
        $settings = $context->settings;

        return [
            'company_name' => $settings->get('general', 'company_name') ?? $settings->get('general', 'legal_name') ?? (tenant('name') ?? config('app.name')),
            'legal_name' => $settings->get('general', 'legal_name'),
            'email' => $settings->get('general', 'email'),
            'phone' => $settings->get('general', 'phone'),
            'address' => $settings->get('general', 'address'),
            'tax_number' => $settings->get('general', 'tax_number'),
            'primary_color' => $settings->get('branding', 'primary_color') ?? '#111827',
            'logo' => $this->logoDataUri($settings->get('branding', 'logo')),
        ];
    }

    /**
     * Embed the logo as a data: URI (remote loading is disabled in the renderer).
     */
    protected function logoDataUri(mixed $logoPath): ?string
    {
        if (! is_string($logoPath) || $logoPath === '') {
            return null;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($logoPath)) {
            return null;
        }

        $mime = $disk->mimeType($logoPath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($logoPath));
    }

    /**
     * Read a private file and return it as a data: URI, or null if unavailable.
     */
    protected function fileDataUri(?string $disk, ?string $path): ?string
    {
        if ($disk === null || $path === null) {
            return null;
        }

        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            return null;
        }

        $mime = $storage->mimeType($path) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode((string) $storage->get($path));
    }
}
