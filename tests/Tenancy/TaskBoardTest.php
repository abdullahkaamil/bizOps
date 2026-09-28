<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\CRM\Models\Customer;
use App\Domain\Tasks\Actions\MoveTaskAction;
use App\Domain\Tasks\Enums\CommentVisibility;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Events\TaskTransitioned;
use App\Domain\Tasks\Exceptions\InvalidTaskTransition;
use App\Domain\Tasks\Models\Board;
use App\Domain\Tasks\Models\BoardColumn;
use App\Domain\Tasks\Models\Task;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

function tkTenant(string $slug = 'alpha'): Tenant
{
    return (new CreateTenant)->handle([
        'name' => ucfirst($slug),
        'subdomain' => $slug,
        'admin_name' => ucfirst($slug).' Owner',
        'admin_email' => "owner@{$slug}.test",
        'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);
}

/**
 * A project board with an internal developer member and an external customer
 * representative member, plus a fresh task (auto-placed in the first column).
 *
 * @return array<string, mixed>
 */
function tkProject(Tenant $tenant): array
{
    return $tenant->run(function (): array {
        $customer = Customer::create(['company_name' => 'Client Co', 'status' => 'active']);

        $board = Board::create([
            'name' => 'Website', 'type' => 'project',
            'customer_id' => $customer->id, 'is_active' => true,
        ]);

        $dev = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        $dev->assignRole(Role::Developer->value);
        $board->members()->attach($dev->id);

        $rep = User::factory()->create(['user_type' => 'external', 'status' => 'active', 'customer_id' => $customer->id]);
        $rep->assignRole(Role::CustomerRepresentative->value);
        $board->members()->attach($rep->id);

        $task = $board->tasks()->create(['title' => 'Build homepage', 'status' => TaskStatus::Todo]);

        return compact('customer', 'board', 'dev', 'rep', 'task');
    });
}

/**
 * An internal board with a developer member and a task.
 *
 * @return array<string, mixed>
 */
function tkInternal(Tenant $tenant): array
{
    return $tenant->run(function (): array {
        $board = Board::create(['name' => 'Ops', 'type' => 'internal', 'is_active' => true]);

        $dev = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        $dev->assignRole(Role::Developer->value);
        $board->members()->attach($dev->id);

        $task = $board->tasks()->create(['title' => 'Rotate keys', 'status' => TaskStatus::Todo]);

        return compact('board', 'dev', 'task');
    });
}

/** Fetch a board's column by workflow category. */
function tkColumn(Board $board, TaskStatus $category): BoardColumn
{
    return BoardColumn::where('board_id', $board->id)
        ->where('category', $category->value)
        ->orderBy('position')
        ->firstOrFail();
}

// ---------------------------------------------------------------------------
// Column seeding & task placement
// ---------------------------------------------------------------------------

test('a new board is seeded with default steps', function () {
    $tenant = tkTenant();
    ['board' => $internal] = tkInternal($tenant);
    ['board' => $project] = tkProject($tenant);

    $tenant->run(function () use ($internal, $project) {
        expect($internal->columns->map(fn ($c) => $c->category->value)->all())
            ->toBe(['todo', 'in_progress', 'completed']);

        // Project boards get an extra customer "review" step.
        expect($project->columns->map(fn ($c) => $c->category->value)->all())
            ->toBe(['todo', 'in_progress', 'review', 'completed']);
    });
});

test('a new task lands in the first (todo) step', function () {
    $tenant = tkTenant();
    ['board' => $board, 'task' => $task] = tkInternal($tenant);

    $tenant->run(function () use ($board, $task) {
        $todo = tkColumn($board, TaskStatus::Todo);
        expect($task->fresh()->board_column_id)->toBe($todo->id)
            ->and($task->fresh()->status)->toBe(TaskStatus::Todo);
    });
});

// ---------------------------------------------------------------------------
// Free movement (action level)
// ---------------------------------------------------------------------------

test('moving a task to a step derives its status and records history + event', function () {
    $tenant = tkTenant();
    ['board' => $board, 'dev' => $dev, 'task' => $task] = tkInternal($tenant);

    Event::fake([TaskTransitioned::class]);

    $tenant->run(function () use ($board, $dev, $task) {
        $inProgress = tkColumn($board, TaskStatus::InProgress);

        (new MoveTaskAction)->handle($task, $dev, $inProgress);
        $task->refresh();

        expect($task->status)->toBe(TaskStatus::InProgress)
            ->and($task->board_column_id)->toBe($inProgress->id);

        $history = $task->statusHistory()->reorder('id')->get();
        expect($history)->toHaveCount(1)
            ->and($history[0]->from_status)->toBe(TaskStatus::Todo)
            ->and($history[0]->to_status)->toBe(TaskStatus::InProgress)
            ->and($history[0]->actor_id)->toBe($dev->id);
    });

    Event::assertDispatched(TaskTransitioned::class, fn (TaskTransitioned $e): bool => $e->taskId === $task->id
        && $e->to === TaskStatus::InProgress);
});

test('moving a task into a terminal step completes it, and out of it reopens it', function () {
    $tenant = tkTenant();
    ['board' => $board, 'dev' => $dev, 'task' => $task] = tkInternal($tenant);

    $tenant->run(function () use ($board, $dev, $task) {
        (new MoveTaskAction)->handle($task, $dev, tkColumn($board, TaskStatus::Completed));
        $task->refresh();
        expect($task->status)->toBe(TaskStatus::Completed)
            ->and($task->completed_at)->not->toBeNull();

        (new MoveTaskAction)->handle($task, $dev, tkColumn($board, TaskStatus::InProgress));
        $task->refresh();
        expect($task->status)->toBe(TaskStatus::InProgress)
            ->and($task->completed_at)->toBeNull();
    });
});

test('a task cannot be moved to a step on another board', function () {
    $tenant = tkTenant();
    ['dev' => $dev, 'task' => $task] = tkInternal($tenant);
    ['board' => $otherBoard] = tkProject($tenant);

    $tenant->run(function () use ($dev, $task, $otherBoard) {
        $foreignColumn = tkColumn($otherBoard, TaskStatus::InProgress);

        expect(fn () => (new MoveTaskAction)->handle($task, $dev, $foreignColumn))
            ->toThrow(InvalidTaskTransition::class);
    });
});

test('a pure reorder within the same step writes no history', function () {
    $tenant = tkTenant();
    ['board' => $board, 'dev' => $dev, 'task' => $task] = tkInternal($tenant);

    $tenant->run(function () use ($board, $dev, $task) {
        $todo = tkColumn($board, TaskStatus::Todo);

        (new MoveTaskAction)->handle($task, $dev, $todo, 0);

        expect($task->statusHistory()->count())->toBe(0);
    });
});

// ---------------------------------------------------------------------------
// Free movement & authorization (HTTP level)
// ---------------------------------------------------------------------------

test('an internal developer can move a task between steps', function () {
    $tenant = tkTenant();
    ['board' => $board, 'dev' => $dev, 'task' => $task] = tkProject($tenant);
    $base = 'http://alpha.kaamil.test';

    $columnId = $tenant->run(fn (): string => tkColumn($board, TaskStatus::Review)->public_id);

    $this->actingAs($dev)
        ->post("{$base}/tasks/{$task->public_id}/move", ['column_id' => $columnId])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe(TaskStatus::Review);
});

test('an external representative can move a task on their project board', function () {
    $tenant = tkTenant();
    ['board' => $board, 'rep' => $rep, 'task' => $task] = tkProject($tenant);
    $base = 'http://alpha.kaamil.test';

    $completedId = $tenant->run(fn (): string => tkColumn($board, TaskStatus::Completed)->public_id);

    // Moving into the "done" step is how a customer approves work now.
    $this->actingAs($rep)
        ->post("{$base}/tasks/{$task->public_id}/move", ['column_id' => $completedId])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe(TaskStatus::Completed);
});

test('an external representative cannot move a task on an internal board', function () {
    $tenant = tkTenant();
    ['rep' => $rep] = tkProject($tenant);
    ['board' => $internal, 'task' => $task] = tkInternal($tenant);
    $base = 'http://alpha.kaamil.test';

    $columnId = $tenant->run(fn (): string => tkColumn($internal, TaskStatus::InProgress)->public_id);

    $this->actingAs($rep)
        ->post("{$base}/tasks/{$task->public_id}/move", ['column_id' => $columnId])
        ->assertForbidden();
});

test('moving a task requires a valid column', function () {
    $tenant = tkTenant();
    ['dev' => $dev, 'task' => $task] = tkInternal($tenant);

    $this->actingAs($dev)
        ->post("http://alpha.kaamil.test/tasks/{$task->public_id}/move", ['column_id' => 'not-a-real-id'])
        ->assertSessionHasErrors('column_id');
});

// ---------------------------------------------------------------------------
// Column management
// ---------------------------------------------------------------------------

test('an internal manager can add, rename and delete a step', function () {
    $tenant = tkTenant();
    ['board' => $board] = tkInternal($tenant);
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());
    $base = 'http://alpha.kaamil.test';

    // Add.
    $this->actingAs($owner)
        ->post("{$base}/boards/{$board->public_id}/columns", ['name' => 'Blokajda', 'category' => 'in_progress'])
        ->assertRedirect();

    $column = $tenant->run(fn () => BoardColumn::where('board_id', $board->id)->where('name', 'Blokajda')->first());
    expect($column)->not->toBeNull();

    // Rename.
    $this->actingAs($owner)
        ->put("{$base}/columns/{$column->public_id}", ['name' => 'Beklemede', 'category' => 'todo'])
        ->assertRedirect();

    $column->refresh();
    expect($column->name)->toBe('Beklemede')
        ->and($column->category)->toBe(TaskStatus::Todo);

    // Delete (it holds no tasks).
    $this->actingAs($owner)
        ->delete("{$base}/columns/{$column->public_id}")
        ->assertRedirect();

    expect($tenant->run(fn () => BoardColumn::find($column->id)))->toBeNull();
});

test('a step holding tasks cannot be deleted', function () {
    $tenant = tkTenant();
    ['board' => $board, 'task' => $task] = tkInternal($tenant);
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());

    $todoId = $tenant->run(fn (): string => tkColumn($board, TaskStatus::Todo)->public_id);

    $this->actingAs($owner)
        ->delete("http://alpha.kaamil.test/columns/{$todoId}")
        ->assertSessionHasErrors('column');

    // The task and its column both survive.
    expect($tenant->run(fn () => Task::whereKey($task->id)->exists()))->toBeTrue();
});

test('external representatives cannot manage steps', function () {
    $tenant = tkTenant();
    ['board' => $board, 'rep' => $rep] = tkProject($tenant);

    $this->actingAs($rep)
        ->post("http://alpha.kaamil.test/boards/{$board->public_id}/columns", ['name' => 'Mine'])
        ->assertForbidden();
});

test('steps can be reordered', function () {
    $tenant = tkTenant();
    ['board' => $board] = tkInternal($tenant);
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());

    $ids = $tenant->run(fn () => $board->columns()->pluck('public_id')->all());
    $reversed = array_reverse($ids);

    $this->actingAs($owner)
        ->post("http://alpha.kaamil.test/boards/{$board->public_id}/columns/reorder", ['columns' => $reversed])
        ->assertRedirect();

    $after = $tenant->run(fn () => $board->columns()->orderBy('position')->pluck('public_id')->all());
    expect($after)->toBe($reversed);
});

// ---------------------------------------------------------------------------
// External isolation & board access
// ---------------------------------------------------------------------------

test('an external representative cannot view an internal board', function () {
    $tenant = tkTenant();
    ['rep' => $rep] = tkProject($tenant);
    ['board' => $internalBoard] = tkInternal($tenant);

    $this->actingAs($rep)
        ->get("http://alpha.kaamil.test/boards/{$internalBoard->public_id}")
        ->assertForbidden();
});

test('an external representative cannot view a project board they do not belong to', function () {
    $tenant = tkTenant();
    ['rep' => $rep] = tkProject($tenant);

    // A second, unrelated project board for a different customer.
    $other = $tenant->run(function () {
        $c = Customer::create(['company_name' => 'Other Co', 'status' => 'active']);

        return Board::create(['name' => 'Secret', 'type' => 'project', 'customer_id' => $c->id, 'is_active' => true]);
    });

    $this->actingAs($rep)
        ->get("http://alpha.kaamil.test/boards/{$other->public_id}")
        ->assertForbidden();
});

test('the board index is filtered to an external representative\'s own boards', function () {
    $tenant = tkTenant();
    ['board' => $board, 'rep' => $rep] = tkProject($tenant);
    tkInternal($tenant); // should never appear for the rep

    $this->actingAs($rep)
        ->get('http://alpha.kaamil.test/boards')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('boards/Index')
            ->has('boards', 1)
            ->where('boards.0.id', $board->public_id));
});

// ---------------------------------------------------------------------------
// Comment & attachment visibility
// ---------------------------------------------------------------------------

test('internal comments are hidden from external representatives', function () {
    $tenant = tkTenant();
    ['board' => $board, 'dev' => $dev, 'rep' => $rep, 'task' => $task] = tkProject($tenant);
    $base = 'http://alpha.kaamil.test';

    // Developer posts one internal and one customer-visible comment.
    $this->actingAs($dev)->post("{$base}/tasks/{$task->public_id}/comments", [
        'body' => 'Internal note', 'visibility' => 'internal',
    ])->assertRedirect();
    $this->actingAs($dev)->post("{$base}/tasks/{$task->public_id}/comments", [
        'body' => 'Hello customer', 'visibility' => 'customer',
    ])->assertRedirect();

    // The rep sees only the customer-visible comment in the task drawer.
    $this->actingAs($rep)
        ->get("{$base}/boards/{$board->public_id}?task={$task->public_id}")
        ->assertInertia(fn ($p) => $p
            ->has('selectedTask.comments', 1)
            ->where('selectedTask.comments.0.body', 'Hello customer')
            // The conversation UI flags authorship: the dev's comment is not the rep's.
            ->where('selectedTask.comments.0.mine', false));
});

test('an external representative cannot post an internal comment', function () {
    $tenant = tkTenant();
    ['rep' => $rep, 'task' => $task] = tkProject($tenant);

    $this->actingAs($rep)
        ->post("http://alpha.kaamil.test/tasks/{$task->public_id}/comments", [
            'body' => 'sneaky', 'visibility' => 'internal',
        ])
        ->assertForbidden();
});

test('internal board comments and attachments default to internal visibility', function () {
    Storage::fake('local');

    $tenant = tkTenant();
    ['dev' => $dev, 'task' => $task] = tkInternal($tenant);
    $base = 'http://alpha.kaamil.test';

    $this->actingAs($dev)
        ->post("{$base}/tasks/{$task->public_id}/comments", ['body' => 'Team note'])
        ->assertRedirect();

    $this->actingAs($dev)
        ->post("{$base}/tasks/{$task->public_id}/attachments", [
            'file' => UploadedFile::fake()->create('plan.txt', 5, 'text/plain'),
        ])
        ->assertRedirect();

    $tenant->run(function () use ($task): void {
        expect($task->comments()->sole()->visibility)->toBe(CommentVisibility::Internal)
            ->and($task->attachments()->sole()->visibility)->toBe(CommentVisibility::Internal);
    });
});

test('project board comments still require an explicit visibility', function () {
    $tenant = tkTenant();
    ['dev' => $dev, 'task' => $task] = tkProject($tenant);

    $this->actingAs($dev)
        ->post("http://alpha.kaamil.test/tasks/{$task->public_id}/comments", ['body' => 'Missing visibility'])
        ->assertSessionHasErrors('visibility');
});

// ---------------------------------------------------------------------------
// Membership & board management
// ---------------------------------------------------------------------------

test('duplicate board membership is prevented', function () {
    $tenant = tkTenant();
    ['board' => $board, 'dev' => $dev] = tkProject($tenant);
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());

    // The owner needs board membership abilities via the owner role (has all perms).
    $this->actingAs($owner)
        ->post("http://alpha.kaamil.test/boards/{$board->public_id}/members", [
            'user_id' => $dev->public_id,
        ])
        ->assertSessionHasErrors('user_id');
});

test('project board member candidates include only representatives of its customer', function () {
    $tenant = tkTenant();
    ['board' => $board, 'customer' => $customer, 'dev' => $dev, 'rep' => $rep] = tkProject($tenant);
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());

    [$customerCandidate, $otherCustomerRepresentative] = $tenant->run(function () use ($customer): array {
        $customerCandidate = User::factory()->create([
            'name' => 'Customer Candidate',
            'user_type' => 'external',
            'status' => 'active',
            'customer_id' => $customer->id,
        ]);
        $otherCustomer = Customer::create(['company_name' => 'Other Co', 'status' => 'active']);
        $otherCustomerRepresentative = User::factory()->create([
            'name' => 'Other Representative',
            'user_type' => 'external',
            'status' => 'active',
            'customer_id' => $otherCustomer->id,
        ]);

        return [$customerCandidate, $otherCustomerRepresentative];
    });

    $this->actingAs($owner)
        ->get("http://alpha.kaamil.test/boards/{$board->public_id}")
        ->assertInertia(fn ($page) => $page
            ->where('assignableUsers', function ($users) use ($dev, $rep): bool {
                $ids = collect($users)->pluck('id')->sort()->values()->all();
                $expected = collect([$dev->public_id, $rep->public_id])->sort()->values()->all();

                return $ids === $expected;
            })
            ->where('memberCandidates', fn ($users): bool => collect($users)
                ->contains('id', $customerCandidate->public_id)
                && ! collect($users)->contains('id', $otherCustomerRepresentative->public_id)));
});

test('a customer representative can be added to their project board', function () {
    $tenant = tkTenant();
    ['board' => $board, 'customer' => $customer] = tkProject($tenant);
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());
    $candidate = $tenant->run(fn () => User::factory()->create([
        'user_type' => 'external',
        'status' => 'active',
        'customer_id' => $customer->id,
    ]));

    $this->actingAs($owner)
        ->post("http://alpha.kaamil.test/boards/{$board->public_id}/members", [
            'user_id' => $candidate->public_id,
        ])
        ->assertRedirect();

    expect($tenant->run(fn (): bool => $board->hasMember($candidate)))->toBeTrue();
});

test('tasks can only be assigned to active board members', function () {
    $tenant = tkTenant();
    ['board' => $board, 'dev' => $dev] = tkInternal($tenant);
    $nonMember = $tenant->run(fn () => User::factory()->create([
        'user_type' => 'internal',
        'status' => 'active',
    ]));

    $this->actingAs($dev)
        ->post("http://alpha.kaamil.test/boards/{$board->public_id}/tasks", [
            'title' => 'Should not be created',
            'assignee_ids' => [$nonMember->public_id],
        ])
        ->assertSessionHasErrors('assignee_ids.0');

    expect($tenant->run(fn (): int => $board->tasks()->count()))->toBe(1)
        ->and($tenant->run(fn (): bool => $board->hasMember($nonMember)))->toBeFalse();
});

test('external users cannot create boards', function () {
    $tenant = tkTenant();
    ['rep' => $rep] = tkProject($tenant);

    $this->actingAs($rep)
        ->post('http://alpha.kaamil.test/boards', [
            'name' => 'Mine', 'type' => 'internal',
        ])
        ->assertForbidden();
});

test('an internal manager can create an internal board and a project board', function () {
    $tenant = tkTenant();
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());
    $customer = $tenant->run(fn () => Customer::create(['company_name' => 'Cust', 'status' => 'active']));
    $base = 'http://alpha.kaamil.test';

    $this->actingAs($owner)->post("{$base}/boards", ['name' => 'Internal Ops', 'type' => 'internal'])->assertRedirect();
    $this->actingAs($owner)->post("{$base}/boards", [
        'name' => 'Client Project', 'type' => 'project', 'customer_id' => $customer->public_id,
    ])->assertRedirect();

    expect($tenant->run(fn () => Board::count()))->toBe(2);
});

// ---------------------------------------------------------------------------
// Per-column move authority (dynamic workflow engine)
// ---------------------------------------------------------------------------

test('a step restricted to customer move-in blocks internal users (Scenario 5C)', function () {
    $tenant = tkTenant();
    ['board' => $board, 'dev' => $dev, 'task' => $task] = tkProject($tenant);

    $tenant->run(function () use ($board, $dev, $task) {
        $done = tkColumn($board, TaskStatus::Completed);
        $done->update(['move_in' => 'external']); // only the customer may complete

        expect(fn () => (new MoveTaskAction)->handle($task->fresh(), $dev, $done->fresh()))
            ->toThrow(AuthorizationException::class);

        // The external representative may move it in.
        $rep = User::where('user_type', 'external')->first();
        (new MoveTaskAction)->handle($task->fresh(), $rep, $done->fresh());
        expect($task->fresh()->status)->toBe(TaskStatus::Completed);
    });
});

test('a step whose move-out is customer-only blocks internal pulls (Scenario 5A)', function () {
    $tenant = tkTenant();
    ['board' => $board, 'dev' => $dev, 'rep' => $rep, 'task' => $task] = tkProject($tenant);

    $tenant->run(function () use ($board, $dev, $rep, $task) {
        // A hand-off step the developer can drop into but only the customer can pull out of.
        $bank = $board->columns()->create([
            'name' => 'Bank', 'category' => 'in_progress',
            'move_in' => 'both', 'move_out' => 'external', 'position' => 10,
        ]);

        (new MoveTaskAction)->handle($task->fresh(), $dev, $bank); // internal may move in

        $review = tkColumn($board, TaskStatus::Review);

        // Internal cannot pull it back out...
        expect(fn () => (new MoveTaskAction)->handle($task->fresh(), $dev, $review))
            ->toThrow(AuthorizationException::class);

        // ...but the customer can.
        (new MoveTaskAction)->handle($task->fresh(), $rep, $review);
        expect($task->fresh()->status)->toBe(TaskStatus::Review);
    });
});

test('an unauthorized column move is rejected with 403 over HTTP', function () {
    $tenant = tkTenant();
    ['board' => $board, 'dev' => $dev, 'task' => $task] = tkProject($tenant);
    $base = 'http://alpha.kaamil.test';

    $reviewId = $tenant->run(function () use ($board, $task) {
        $bank = $board->columns()->create([
            'name' => 'Bank', 'category' => 'in_progress',
            'move_in' => 'both', 'move_out' => 'external', 'position' => 10,
        ]);
        $task->update(['board_column_id' => $bank->id]); // park it in the hand-off step

        return tkColumn($board, TaskStatus::Review)->public_id;
    });

    $this->actingAs($dev)
        ->post("{$base}/tasks/{$task->public_id}/move", ['column_id' => $reviewId])
        ->assertForbidden();
});

test('a step can be created with custom move-in/move-out authority', function () {
    $tenant = tkTenant();
    ['board' => $board] = tkProject($tenant);
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());

    $this->actingAs($owner)
        ->post("http://alpha.kaamil.test/boards/{$board->public_id}/columns", [
            'name' => 'Waiting on customer', 'category' => 'in_progress',
            'move_in' => 'internal', 'move_out' => 'external',
        ])
        ->assertRedirect();

    $column = $tenant->run(fn () => BoardColumn::where('board_id', $board->id)->where('name', 'Waiting on customer')->first());
    expect($column->move_in->value)->toBe('internal')
        ->and($column->move_out->value)->toBe('external');
});

test('a task title and description are immutable after creation', function () {
    $tenant = tkTenant();
    ['dev' => $dev, 'task' => $task] = tkInternal($tenant);
    $base = 'http://alpha.kaamil.test';

    $tenant->run(fn () => $task->update(['description' => 'Original spec', 'title' => 'Rotate keys']));

    $this->actingAs($dev)
        ->put("{$base}/tasks/{$task->public_id}", [
            'title' => 'Hacked title', 'description' => 'Hacked spec', 'priority' => 'high',
        ])
        ->assertRedirect();

    $fresh = $tenant->run(fn () => $task->fresh());
    expect($fresh->title)->toBe('Rotate keys')
        ->and($fresh->description)->toBe('Original spec')
        ->and($fresh->priority->value)->toBe('high'); // metadata still editable
});
