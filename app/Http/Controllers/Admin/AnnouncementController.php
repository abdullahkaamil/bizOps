<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Central\Models\Announcement;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/announcements/Index', [
            'announcements' => Announcement::query()->latest('id')->get()
                ->map(fn (Announcement $a): array => [
                    'id' => $a->public_id,
                    'title' => $a->title,
                    'body' => $a->body,
                    'level' => $a->level,
                    'is_active' => $a->is_active,
                ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'level' => ['required', Rule::in(['info', 'warning', 'critical'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        Announcement::create($data + ['is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement published.')]);

        return back();
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement removed.')]);

        return back();
    }
}
