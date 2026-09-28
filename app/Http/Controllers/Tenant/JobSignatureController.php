<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Jobs\Models\Job;
use App\Domain\Jobs\Models\JobSignature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreJobSignatureRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobSignatureController extends Controller
{
    public function store(StoreJobSignatureRequest $request, Job $job): RedirectResponse
    {
        $this->authorize('updateServiceData', $job);

        $file = $request->file('file');
        $directory = 'job-signatures/'.tenant('id').'/'.$job->id;
        $path = $file->store($directory, 'local');

        $job->signatures()->create([
            'signed_by_name' => $request->validated('signed_by_name'),
            'signed_by_role' => $request->validated('signed_by_role'),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'created_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Signature captured.')]);

        return back();
    }

    public function download(JobSignature $signature): StreamedResponse
    {
        $signature->load('job');
        $this->authorize('view', $signature->job);

        return Storage::disk($signature->disk)->response($signature->path);
    }
}
