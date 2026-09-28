<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Tasks\Enums\ColumnAccess;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Board;
use App\Domain\Tasks\Models\BoardColumn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ReorderBoardColumnsRequest;
use App\Http\Requests\Tenant\StoreBoardColumnRequest;
use App\Http\Requests\Tenant\UpdateBoardColumnRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Manage a board's steps (columns). Column management is an internal-only action
 * (BoardPolicy@manageColumns); external representatives may move tasks between
 * existing columns but never reshape the board itself.
 */
class BoardColumnController extends Controller
{
    public function store(StoreBoardColumnRequest $request, Board $board): RedirectResponse
    {
        $this->authorize('manageColumns', $board);

        $data = $request->validated();

        $board->columns()->create([
            'name' => $data['name'],
            'category' => $data['category'] ?? TaskStatus::InProgress->value,
            'move_in' => $data['move_in'] ?? ColumnAccess::Both->value,
            'move_out' => $data['move_out'] ?? ColumnAccess::Both->value,
            'position' => (int) $board->columns()->max('position') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Step added.')]);

        return back();
    }

    public function update(UpdateBoardColumnRequest $request, BoardColumn $column): RedirectResponse
    {
        $this->authorize('manageColumns', $column->board);

        $data = $request->validated();

        $column->update([
            'name' => $data['name'],
            'category' => $data['category'] ?? $column->category->value,
            'move_in' => $data['move_in'] ?? $column->move_in->value,
            'move_out' => $data['move_out'] ?? $column->move_out->value,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Step updated.')]);

        return back();
    }

    public function reorder(ReorderBoardColumnsRequest $request, Board $board): RedirectResponse
    {
        $this->authorize('manageColumns', $board);

        DB::transaction(function () use ($request, $board): void {
            foreach ($request->validated('columns') as $position => $publicId) {
                $board->columns()->where('public_id', $publicId)->update(['position' => $position]);
            }
        });

        return back();
    }

    public function destroy(BoardColumn $column): RedirectResponse
    {
        $this->authorize('manageColumns', $column->board);

        if ($column->tasks()->exists()) {
            return back()->withErrors([
                'column' => __('Move its tasks to another step before deleting this one.'),
            ]);
        }

        if ($column->board->columns()->count() <= 1) {
            return back()->withErrors(['column' => __('A board must have at least one step.')]);
        }

        $column->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Step deleted.')]);

        return back();
    }
}
