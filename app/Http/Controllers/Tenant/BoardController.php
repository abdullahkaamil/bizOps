<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\CRM\Models\Customer;
use App\Domain\Tasks\Enums\BoardType;
use App\Domain\Tasks\Enums\ColumnAccess;
use App\Domain\Tasks\Enums\Priority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Board;
use App\Domain\Tasks\Models\Task;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\SerializesBoardData;
use App\Http\Requests\Tenant\StoreBoardRequest;
use App\Http\Requests\Tenant\UpdateBoardRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    use SerializesBoardData;

    /**
     * Board index. Internal staff see every board; external representatives see
     * only the project boards they belong to.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Board::class);

        $viewer = $request->user();

        $boards = Board::query()
            ->with('customer')
            ->withCount(['tasks', 'members'])
            ->when($viewer->isExternal(), fn (Builder $q) => $q
                ->where('type', BoardType::Project->value)
                ->whereHas('members', fn (Builder $m) => $m->whereKey($viewer->id)))
            ->orderBy('name')
            ->get()
            ->map(fn (Board $board): array => $this->boardSummary($board))
            ->all();

        return Inertia::render('boards/Index', [
            'boards' => $boards,
            'boardTypes' => BoardType::values(),
            'customers' => $viewer->isInternal() ? $this->customerOptions() : [],
            'canCreate' => $viewer->can('create', Board::class),
        ]);
    }

    public function store(StoreBoardRequest $request): RedirectResponse
    {
        $this->authorize('create', Board::class);

        $data = $request->validated();
        $customerId = null;

        if (($data['type'] ?? null) === BoardType::Project->value && ! empty($data['customer_id'])) {
            $customerId = Customer::where('public_id', $data['customer_id'])->value('id');
        }

        $board = Board::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'customer_id' => $customerId,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        // The creator is always a member so they can immediately manage the board.
        $board->members()->syncWithoutDetaching([$request->user()->id => ['role' => 'owner']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Board created.')]);

        return to_route('tenant.boards.show', $board);
    }

    /**
     * The Kanban view for a single board. Tasks are grouped into columns by
     * status; the optional `task` query parameter deep-loads a task drawer.
     */
    public function show(Request $request, Board $board): Response
    {
        $this->authorize('view', $board);

        $viewer = $request->user();

        $board->load([
            'customer',
            'members',
            'columns',
            'tasks' => fn ($q) => $q->with(['assignees', 'column'])->orderByDesc('id'),
        ]);

        $columns = $this->boardColumns($board, $viewer);

        $selectedTask = null;
        if ($request->filled('task')) {
            $task = $board->tasks->firstWhere('public_id', $request->string('task')->toString());
            if ($task) {
                $task->load(['comments.author', 'attachments.uploader', 'statusHistory.actor', 'assignees']);
                $selectedTask = $this->taskDetail($task, $viewer);
            }
        }

        return Inertia::render('boards/Show', [
            'board' => [
                ...$this->boardSummary($board),
                'members' => $this->memberRows($board),
                'abilities' => [
                    'update' => $viewer->can('update', $board),
                    'delete' => $viewer->can('delete', $board),
                    'manage_members' => $viewer->can('manageMembers', $board),
                    'manage_columns' => $viewer->can('manageColumns', $board),
                    'create_task' => $viewer->can('create', [Task::class, $board]),
                ],
            ],
            'columns' => $columns,
            'categories' => $this->columnCategories(),
            'accessOptions' => ColumnAccess::options(),
            'selectedTask' => $selectedTask,
            'priorities' => Priority::values(),
            'assignableUsers' => $viewer->isInternal() ? $this->assignableUsers($board) : [],
            'memberCandidates' => $viewer->can('manageMembers', $board) ? $this->memberCandidates($board) : [],
        ]);
    }

    public function update(UpdateBoardRequest $request, Board $board): RedirectResponse
    {
        $this->authorize('update', $board);

        $board->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Board updated.')]);

        return back();
    }

    public function destroy(Board $board): RedirectResponse
    {
        $this->authorize('delete', $board);

        $board->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Board deleted.')]);

        return to_route('tenant.boards.index');
    }

    /**
     * Board members with their membership role, read straight from the pivot
     * table to keep the payload simple and well-typed.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function memberRows(Board $board): array
    {
        return DB::table('board_users')
            ->join('users', 'users.id', '=', 'board_users.user_id')
            ->where('board_users.board_id', $board->id)
            ->orderBy('users.name')
            ->get(['users.public_id', 'users.name', 'board_users.role'])
            ->map(fn (object $row): array => [
                'id' => $row->public_id,
                'name' => $row->name,
                'role' => $row->role,
            ])->all();
    }

    /**
     * The workflow buckets a column can map to, with localized labels — used by
     * the column editor so a step can be tied to a status for dashboards/reports.
     *
     * @return array<int, array{value: string, label: string}>
     */
    protected function columnCategories(): array
    {
        return array_map(
            static fn (TaskStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            TaskStatus::cases(),
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function customerOptions(): array
    {
        return Customer::query()->orderBy('company_name')->get()
            ->map(fn (Customer $c): array => ['id' => $c->public_id, 'name' => $c->company_name])
            ->all();
    }

    /**
     * Active board members who can be assigned to tasks.
     *
     * @return array<int, array<string, string>>
     */
    protected function assignableUsers(Board $board): array
    {
        return $board->members()
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u): array => $this->userChip($u))
            ->all();
    }

    /**
     * Active users who may be added to the board. Project boards include the
     * representatives of their customer alongside the tenant's internal users.
     *
     * @return array<int, array<string, string>>
     */
    protected function memberCandidates(Board $board): array
    {
        $memberIds = $board->members()->pluck('users.id');

        return User::query()
            ->where('status', 'active')
            ->where(function (Builder $query) use ($board): void {
                $query->where('user_type', 'internal');

                if ($board->isProject() && $board->customer_id !== null) {
                    $query->orWhere(function (Builder $query) use ($board): void {
                        $query->where('user_type', 'external')
                            ->where('customer_id', $board->customer_id);
                    });
                }
            })
            ->whereNotIn('id', $memberIds)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => $this->userChip($user))
            ->all();
    }
}
