<?php

declare(strict_types=1);

namespace App\Domain\Documents\Generators;

use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Support\DocumentContext;
use App\Domain\Jobs\Models\Job;
use App\Domain\Jobs\Models\JobImage;
use App\Domain\Workshop\Models\WorkshopTicket;

/**
 * Branded service report for a completed (or in-progress) field job.
 */
class JobServiceReportGenerator extends AbstractDocumentGenerator
{
    public function type(): DocumentType
    {
        return DocumentType::JobServiceReport;
    }

    protected function view(): string
    {
        return 'documents.job-service-report';
    }

    protected function number(DocumentContext $context): ?string
    {
        return $this->job($context)->number;
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadata(DocumentContext $context): array
    {
        return ['job' => $this->job($context)->public_id];
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(DocumentContext $context): array
    {
        $job = $this->job($context);
        $job->loadMissing(['customer', 'contact', 'serviceAddress', 'assignee', 'images', 'signatures']);
        $settings = $context->settings;

        $fmt = fn ($ts) => $ts !== null ? $settings->formatDateTime($ts) : null;

        $signature = $job->signatures->last();

        return [
            'job' => $job,
            'number' => $job->number,
            'date' => $settings->formatDateTime(now()),
            'planned_at' => $fmt($job->planned_at),
            'actual_start_at' => $fmt($job->actual_start_at),
            'actual_end_at' => $fmt($job->actual_end_at),
            'duration_minutes' => $job->durationMinutes(),
            'customer' => $job->customer,
            'contact' => $job->contact,
            'address' => $job->serviceAddress,
            'assignee' => $job->assignee,
            'service_notes' => $job->service_notes,
            // Embedded, private image data URIs (missing images simply yield fewer cells).
            'images' => $job->images
                ->map(fn (JobImage $i): ?string => $this->fileDataUri($i->disk, $i->path))
                ->filter()
                ->values()
                ->all(),
            'signature' => $signature !== null
                ? $this->fileDataUri($signature->disk, $signature->path)
                : null,
            'signed_by' => $signature?->signed_by_name,
            'workshop_devices' => WorkshopTicket::query()
                ->where('job_id', $job->id)
                ->with('device')
                ->get()
                ->map(function (WorkshopTicket $t): string {
                    $device = trim($t->device->brand.' '.$t->device->model);
                    $serial = $t->device->serial_number;

                    return trim($t->number.' — '.$device.($serial ? " (SN {$serial})" : '').' · '.$t->status->label());
                })->all(),
        ];
    }

    private function job(DocumentContext $context): Job
    {
        /** @var Job $job */
        $job = $context->related;

        return $job;
    }
}
