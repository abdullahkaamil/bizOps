<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Tasks\Models\Board;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AddBoardMemberRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BoardMemberController extends Controller
{
    public function store(AddBoardMemberRequest $request, Board $board): RedirectResponse
    {
        $this->authorize('manageMembers', $board);

        $user = User::where('public_id', $request->validated('user_id'))->firstOrFail();

        // External representatives may only join project boards for their own customer.
        if ($user->isExternal() && (! $board->isProject() || $board->customer_id !== $user->customer_id)) {
            throw ValidationException::withMessages([
                'user_id' => __('This representative cannot be added to this board.'),
            ]);
        }

        if ($board->hasMember($user)) {
            throw ValidationException::withMessages([
                'user_id' => __('This user is already a member of the board.'),
            ]);
        }

        $board->members()->attach($user->id, ['role' => $request->validated('role')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member added.')]);

        return back();
    }

    public function destroy(Board $board, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $board);

        $board->members()->detach($user->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return back();
    }
}
