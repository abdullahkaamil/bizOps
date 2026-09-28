<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Jobs;

use App\Domain\Jobs\Models\JobImage;
use App\Domain\Jobs\Services\ImageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Compresses a job photo and generates its thumbnail off the request cycle.
 * Runs inside the correct tenant (stancl's QueueTenancyBootstrapper). If GD is
 * unavailable or the payload is unprocessable, the original is kept and the row
 * is simply marked processed.
 */
class ProcessJobImage implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $jobImagePublicId) {}

    public function handle(ImageProcessor $processor): void
    {
        $image = JobImage::where('public_id', $this->jobImagePublicId)->first();

        if ($image === null || $image->processed) {
            return;
        }

        $disk = Storage::disk($image->disk);

        if (! $disk->exists($image->path) || ! $processor->isSupported()) {
            $image->update(['processed' => true]);

            return;
        }

        try {
            $result = $processor->process((string) $disk->get($image->path));
        } catch (Throwable) {
            $image->update(['processed' => true]);

            return;
        }

        $thumbnailPath = $image->path.'.thumb.jpg';
        $disk->put($image->path, $result['image']);
        $disk->put($thumbnailPath, $result['thumbnail']);

        $image->update([
            'thumbnail_path' => $thumbnailPath,
            'mime_type' => $result['mime'],
            'size_bytes' => strlen($result['image']),
            'processed' => true,
        ]);
    }
}
