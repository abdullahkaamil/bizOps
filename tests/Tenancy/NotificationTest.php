<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\CRM\Models\Customer;
use App\Domain\Notifications\Enums\EmailStatus;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\EmailLog;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Notifications\TaskAssignedNotification;
use App\Domain\Notifications\Notifications\TaskMovedNotification;
use App\Domain\Tasks\Actions\MoveTaskAction;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Board;
use App\Domain\Tasks\Models\BoardColumn;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

function ntTenant(): Tenant
{
    return (new CreateTenant)->handle([
        'name' => 'Alpha',
        'subdomain' => 'alpha',
        'admin_name' => 'Alpha Owner',
        'admin_email' => 'owner@alpha.test',
        'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);
}

/**
 * A project board where the developer is both creator and assignee (so the
 * internal team is notified on approve/reject) plus an external reviewer.
 *
 * @return array<string, mixed>
 */
function ntScenario(Tenant $tenant): array
{
    return $tenant->run(function (): array {
        $customer = Customer::create(['company_name' => 'Client Co', 'status' => 'active']);
        $board = Board::create(['name' => 'Site', 'type' => 'project', 'customer_id' => $customer->id, 'is_active' => true]);

        $dev = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        $dev->assignRole(Role::Developer->value);
        $board->members()->attach($dev->id);

        $rep = User::factory()->create(['user_type' => 'external', 'status' => 'active', 'customer_id' => $customer->id]);
        $rep->assignRole(Role::CustomerRepresentative->value);
        $board->members()->attach($rep->id);

        $task = $board->tasks()->create(['title' => 'Landing page', 'status' => TaskStatus::Todo, 'created_by' => $dev->id]);
        $task->assignees()->attach($dev->id);

        return compact('board', 'dev', 'rep', 'task');
    });
}

/** Fetch a board's column by workflow category. */
function ntColumn(Board $board, TaskStatus $category): BoardColumn
{
    return BoardColumn::where('board_id', $board->id)
        ->where('category', $category->value)
        ->orderBy('position')
        ->firstOrFail();
}

test('queued notifications are stored centrally with tenant context and initialize the tenant when processed', function () {
    // Use the database queue driver, pinned to the CENTRAL connection.
    config(['queue.default' => 'database']);
    expect(config('queue.connections.database.connection'))->toBe('pgsql');

    $tenant = ntTenant();
    ['dev' => $dev, 'task' => $task] = ntScenario($tenant);

    // Dispatch a queued notification from inside the tenant.
    $tenant->run(fn () => $dev->notify(new TaskAssignedNotification(
        $task->id, $task->title, $task->public_id, $task->board->public_id,
    )));

    // The job is stored in the CENTRAL jobs table, carrying the tenant id.
    $job = DB::connection('pgsql')->table('jobs')->first();
    expect($job)->not->toBeNull()
        ->and($job->payload)->toContain($tenant->id);

    // Nothing has been written to the tenant db yet.
    expect($tenant->run(fn () => $dev->notifications()->count()))->toBe(0);

    // Processing the job re-initializes the tenant, so the notification lands in
    // the correct tenant database.
    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    expect($tenant->run(fn () => $dev->notifications()->count()))->toBe(1);
});

test('moving a task back notifies the other board members with a move notice', function () {
    $tenant = ntTenant();
    ['board' => $board, 'dev' => $dev, 'rep' => $rep, 'task' => $task] = ntScenario($tenant);

    $tenant->run(function () use ($board, $dev, $rep, $task) {
        // Developer submits into review (customer's court); customer sends it back.
        (new MoveTaskAction)->handle($task, $dev, ntColumn($board, TaskStatus::Review));
        (new MoveTaskAction)->handle($task->fresh(), $rep, ntColumn($board, TaskStatus::InProgress));

        // The developer is told the task moved (broad notice; In Progress has no
        // single owner). The action-needed alert on submit went to the customer.
        $moves = $dev->notifications()->get()
            ->filter(fn ($n) => $n->data['type'] === NotificationType::TaskMoved->value);

        expect($moves)->toHaveCount(1);
    });
});

test('a task email is branded with the tenant name', function () {
    $tenant = ntTenant();
    ['dev' => $dev, 'task' => $task] = ntScenario($tenant);

    $tenant->run(function () use ($dev, $task) {
        $mail = (new TaskMovedNotification(
            $task->id, $task->title, $task->public_id, $task->board->public_id, 'İncelemede',
        ))->toMail($dev);

        // ntTenant() names the tenant "Alpha"; the subject is Turkish (default locale).
        expect($mail->subject)->toContain('Alpha')
            ->and($mail->subject)->toContain('taşındı');
    });
});

test('a failed email is logged with the error and correlation id', function () {
    // Register a transport that always throws.
    Mail::extend('boom', fn () => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new RuntimeException('smtp is down');
        }

        public function __toString(): string
        {
            return 'boom';
        }
    });
    config(['mail.mailers.boom' => ['transport' => 'boom'], 'mail.default' => 'boom']);

    $tenant = ntTenant();
    ['dev' => $dev, 'task' => $task] = ntScenario($tenant);

    $tenant->run(function () use ($dev, $task) {
        $notification = new TaskAssignedNotification(
            $task->id, $task->title, $task->public_id, $task->board->public_id, 'corr-123',
        );

        try {
            $dev->notifyNow($notification, ['tenant-mail']);
        } catch (Throwable) {
            // Expected: the transport throws; the channel logs then re-throws.
        }

        $log = EmailLog::where('status', EmailStatus::Failed)->first();
        expect($log)->not->toBeNull()
            ->and($log->recipient)->toBe($dev->email)
            ->and($log->error_message)->toContain('smtp is down')
            ->and($log->correlation_id)->toBe('corr-123')
            ->and($log->notification_type)->toBe(NotificationType::TaskAssigned->value);
    });
});

test('a retried in-app notification does not create a duplicate', function () {
    $tenant = ntTenant();
    ['dev' => $dev, 'task' => $task] = ntScenario($tenant);

    $tenant->run(function () use ($dev, $task) {
        // Same correlation id + related task + recipient => deterministic id.
        $notification = new TaskAssignedNotification(
            $task->id, $task->title, $task->public_id, $task->board->public_id, 'fixed-correlation',
        );

        $dev->notifyNow($notification, ['tenant-database']);
        $dev->notifyNow($notification, ['tenant-database']);

        expect($dev->notifications()->count())->toBe(1);
    });
});

test('a task entering the customer review step targets the customer with an action alert', function () {
    $tenant = ntTenant();
    ['dev' => $dev, 'rep' => $rep, 'task' => $task] = ntScenario($tenant);

    $tenant->run(function () use ($task, $dev, $rep) {
        $board = $task->board;

        // Submitting into review (only the customer moves it onward) targets the rep.
        (new MoveTaskAction)->handle($task, $dev, ntColumn($board, TaskStatus::Review));

        expect($rep->notifications()->count())->toBe(1)
            ->and($rep->notifications()->first()->data['type'])->toBe(NotificationType::TaskActionNeeded->value);

        // The customer approves by moving it to "done"; the developer gets the broad
        // move notice, and the customer (the actor) is not notified again.
        (new MoveTaskAction)->handle($task->fresh(), $rep, ntColumn($board, TaskStatus::Completed));

        expect($rep->notifications()->count())->toBe(1) // still just the action alert
            ->and($dev->notifications()->get()->contains(
                fn ($n) => $n->data['type'] === NotificationType::TaskMoved->value,
            ))->toBeTrue();
    });
});

test('a new task notifies every other board member', function () {
    $tenant = ntTenant();
    ['board' => $board, 'dev' => $dev, 'rep' => $rep] = ntScenario($tenant);

    $this->actingAs($dev)
        ->post("http://alpha.kaamil.test/boards/{$board->public_id}/tasks", ['title' => 'Fix the footer'])
        ->assertRedirect();

    $tenant->run(function () use ($dev, $rep) {
        // Every other member (the customer rep) is told; the creator is not.
        expect($rep->notifications()->get()->contains(fn ($n) => $n->data['type'] === NotificationType::TaskCreated->value))->toBeTrue()
            ->and($dev->notifications()->get()->contains(fn ($n) => $n->data['type'] === NotificationType::TaskCreated->value))->toBeFalse();
    });
});

test('an internal comment never notifies the external representative', function () {
    $tenant = ntTenant();
    ['dev' => $dev, 'rep' => $rep, 'task' => $task] = ntScenario($tenant);
    $base = 'http://alpha.kaamil.test';

    // Internal-only comment -> the customer is NOT notified.
    $this->actingAs($dev)->post("{$base}/tasks/{$task->public_id}/comments", [
        'body' => 'Internal note', 'visibility' => 'internal',
    ])->assertRedirect();

    // Customer-visible comment -> the customer IS notified.
    $this->actingAs($dev)->post("{$base}/tasks/{$task->public_id}/comments", [
        'body' => 'Hello customer', 'visibility' => 'customer',
    ])->assertRedirect();

    $tenant->run(function () use ($rep) {
        $commented = $rep->notifications()->get()
            ->filter(fn ($n) => $n->data['type'] === NotificationType::TaskCommented->value);

        // Exactly one — the customer-visible comment only.
        expect($commented)->toHaveCount(1);
    });
});

test('a user email opt-out removes the mail channel', function () {
    $tenant = ntTenant();
    ['dev' => $dev, 'task' => $task] = ntScenario($tenant);

    $tenant->run(function () use ($dev, $task) {
        NotificationPreference::create([
            'user_id' => $dev->id,
            'notification_type' => NotificationType::TaskAssigned->value,
            'email_enabled' => false,
            'in_app_enabled' => true,
        ]);

        $channels = (new TaskAssignedNotification(
            $task->id, $task->title, $task->public_id, $task->board->public_id,
        ))->via($dev);

        expect($channels)->toBe(['tenant-database']);
    });
});
