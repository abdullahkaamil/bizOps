<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Jobs;

use App\Domain\Jobs\Services\ImageProcessor;
use App\Domain\Workshop\Models\WorkshopAttachment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Compresses a workshop attachment and generates its thumbnail off the request
 * cycle, using the shared ImageProcessor. Runs in the correct tenant context.
 */
class ProcessWorkshopImage implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $attachmentPublicId) {}

    public function handle(ImageProcessor $processor): void
    {
        $attachment = WorkshopAttachment::where('public_id', $this->attachmentPublicId)->first();

        if ($attachment === null || $attachment->processed) {
            return;
        }

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path) || ! $processor->isSupported()) {
            $attachment->update(['processed' => true]);

            return;
        }

        try {
            $result = $processor->process((string) $disk->get($attachment->path));
        } catch (Throwable) {
            $attachment->update(['processed' => true]);

            return;
        }

        $thumbnailPath = $attachment->path.'.thumb.jpg';
        $disk->put($attachment->path, $result['image']);
        $disk->put($thumbnailPath, $result['thumbnail']);

        $attachment->update([
            'thumbnail_path' => $thumbnailPath,
            'mime_type' => $result['mime'],
            'size_bytes' => strlen($result['image']),
            'processed' => true,
        ]);
    }
}
