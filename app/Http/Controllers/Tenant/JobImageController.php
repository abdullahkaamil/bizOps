<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Jobs\Jobs\ProcessJobImage;
use App\Domain\Jobs\Models\Job;
use App\Domain\Jobs\Models\JobImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreJobImageRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobImageController extends Controller
{
    public function store(StoreJobImageRequest $request, Job $job): RedirectResponse
    {
        $this->authorize('updateServiceData', $job);

        $file = $request->file('file');
        $directory = 'job-images/'.tenant('id').'/'.$job->id;
        $path = $file->store($directory, 'local');

        $image = $job->images()->create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'sort_order' => (int) $job->images()->max('sort_order') + 1,
            'processed' => false,
            'created_at' => now(),
        ]);

        // Compress + thumbnail off the request cycle (runs in tenant context).
        ProcessJobImage::dispatch($image->public_id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo uploaded.')]);

        return back();
    }

    public function destroy(JobImage $image): RedirectResponse
    {
        $image->load('job');
        $this->authorize('updateServiceData', $image->job);

        $disk = Storage::disk($image->disk);
        $disk->delete($image->path);
        if ($image->thumbnail_path !== null) {
            $disk->delete($image->thumbnail_path);
        }
        $image->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo removed.')]);

        return back();
    }

    public function download(Request $request, JobImage $image): StreamedResponse
    {
        $image->load('job');
        $this->authorize('view', $image->job);

        $wantsThumb = $request->boolean('thumb') && $image->thumbnail_path !== null;
        $path = $wantsThumb ? $image->thumbnail_path : $image->path;

        return Storage::disk($image->disk)->response($path);
    }
}
