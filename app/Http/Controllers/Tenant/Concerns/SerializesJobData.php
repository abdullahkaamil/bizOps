<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Concerns;

use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Jobs\Models\Job;
use App\Domain\Jobs\Models\JobImage;
use App\Domain\Jobs\Models\JobSignature;
use App\Domain\Jobs\Models\JobStatusHistory;
use App\Models\User;

/**
 * Shared serialization for job payloads. Every payload embeds the viewer's
 * server-computed abilities so the mobile UI only mirrors authorization.
 */
trait SerializesJobData
{
    /**
     * @return array<string, mixed>
     */
    protected function jobRow(Job $job): array
    {
        return [
            'id' => $job->public_id,
            'number' => $job->number,
            'title' => $job->title,
            'status' => $job->status->value,
            'planned_at' => $job->planned_at?->toIso8601String(),
            'customer' => $job->relationLoaded('customer') && $job->customer
                ? $job->customer->company_name : null,
            'assignee' => $job->relationLoaded('assignee') && $job->assignee
                ? $job->assignee->name : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function jobDetail(Job $job, User $viewer): array
    {
        return [
            ...$this->jobRow($job),
            'description' => $job->description,
            'service_notes' => $job->service_notes,
            'internal_notes' => $job->internal_notes,
            'actual_start_at' => $job->actual_start_at?->toIso8601String(),
            'actual_end_at' => $job->actual_end_at?->toIso8601String(),
            'duration_minutes' => $job->durationMinutes(),
            'customer' => $job->customer ? [
                'name' => $job->customer->company_name,
                'email' => $job->customer->email,
                'phone' => $job->customer->phone,
            ] : null,
            'contact' => $job->contact ? [
                'name' => trim($job->contact->first_name.' '.$job->contact->last_name),
                'phone' => $job->contact->phone,
                'email' => $job->contact->email,
            ] : null,
            'address' => $job->serviceAddress ? [
                'lines' => array_values(array_filter([
                    $job->serviceAddress->address_line_1,
                    $job->serviceAddress->address_line_2,
                    trim($job->serviceAddress->postal_code.' '.$job->serviceAddress->city),
                    $job->serviceAddress->country_code,
                ])),
            ] : null,
            'assignee' => $job->assignee ? ['id' => $job->assignee->public_id, 'name' => $job->assignee->name] : null,
            'images' => $job->images->map(fn (JobImage $i): array => [
                'id' => $i->public_id,
                'name' => $i->original_name,
                'processed' => $i->processed,
                'url' => route('tenant.jobs.images.download', $i->public_id),
                'thumb_url' => route('tenant.jobs.images.download', ['image' => $i->public_id, 'thumb' => 1]),
            ])->all(),
            'signature' => $job->signatures->map(fn (JobSignature $s): array => [
                'id' => $s->public_id,
                'signed_by_name' => $s->signed_by_name,
                'url' => route('tenant.jobs.signature.download', $s->public_id),
            ])->last(),
            'history' => $job->statusHistory->map(fn (JobStatusHistory $h): array => [
                'id' => $h->public_id,
                'from' => $h->from_status?->value,
                'to' => $h->to_status->value,
                'metadata' => $h->metadata,
                'actor' => $h->actor?->name,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->all(),
            'documents' => GeneratedDocument::query()
                ->where('related_type', Job::class)
                ->where('related_id', $job->id)
                ->latest('id')
                ->get()
                ->map(fn (GeneratedDocument $d): array => [
                    'id' => $d->public_id,
                    'type' => $d->document_type->label(),
                    'number' => $d->number,
                    'generated_at' => $d->generated_at?->toIso8601String(),
                    'url' => $d->temporaryDownloadUrl(),
                ])->all(),
            'abilities' => [
                'start' => $viewer->can('start', $job),
                'complete' => $viewer->can('complete', $job),
                'cancel' => $viewer->can('cancel', $job),
                'reopen' => $viewer->can('reopen', $job),
                'assign' => $viewer->can('assign', $job),
                'edit' => $viewer->can('update', $job),
                'service_data' => $viewer->can('updateServiceData', $job),
                'generate_report' => $viewer->can('generateReport', $job),
            ],
        ];
    }
}
