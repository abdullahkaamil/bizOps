<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Workshop\Jobs\ProcessWorkshopImage;
use App\Domain\Workshop\Models\WorkshopAttachment;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreWorkshopAttachmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkshopAttachmentController extends Controller
{
    public function store(StoreWorkshopAttachmentRequest $request, WorkshopTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $file = $request->file('file');
        $path = $file->store('workshop-attachments/'.tenant('id').'/'.$ticket->id, 'local');

        $attachment = $ticket->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'kind' => $request->validated('kind') ?? 'repair',
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'processed' => false,
            'created_at' => now(),
        ]);

        ProcessWorkshopImage::dispatch($attachment->public_id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Image uploaded.')]);

        return back();
    }

    public function destroy(WorkshopAttachment $attachment): RedirectResponse
    {
        $attachment->load('ticket');
        $this->authorize('update', $attachment->ticket);

        $disk = Storage::disk($attachment->disk);
        $disk->delete($attachment->path);
        if ($attachment->thumbnail_path !== null) {
            $disk->delete($attachment->thumbnail_path);
        }
        $attachment->delete();

        return back();
    }

    public function download(Request $request, WorkshopAttachment $attachment): StreamedResponse
    {
        $attachment->load('ticket');
        $this->authorize('view', $attachment->ticket);

        $wantsThumb = $request->boolean('thumb') && $attachment->thumbnail_path !== null;
        $path = $wantsThumb ? $attachment->thumbnail_path : $attachment->path;

        return Storage::disk($attachment->disk)->response($path);
    }
}
