<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\CRM\Models\Customer;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Board;
use App\Domain\Tasks\Models\Task;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\ActiveTenants;
use Illuminate\Support\Facades\Artisan;

function qsTenant(string $slug = 'alpha'): Tenant
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
 * Seed one internal, active assignee on a task due within the window. Returns the
 * assignee's id so the test can assert their in-app notifications.
 */
function qsSeedDueTask(Tenant $tenant): int
{
    return $tenant->run(function (): int {
        $board = Board::create(['name' => 'Ops', 'type' => 'internal', 'is_active' => true]);
        $user = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);

        $task = Task::create([
            'board_id' => $board->id,
            'title' => 'Ship it',
            'status' => TaskStatus::Todo,
            'due_at' => now()->addHours(2),
            'created_by' => $user->id,
        ]);
        $task->assignees()->attach($user->id);

        return $user->id;
    });
}

test('the reminder scheduler initializes the correct tenant and notifies its assignees', function () {
    $tenant = qsTenant();
    $userId = qsSeedDueTask($tenant);

    Artisan::call('tasks:notify-due-soon');

    $count = $tenant->run(fn () => User::find($userId)->notifications()->count());
    expect($count)->toBe(1);
});

test('a suspended tenant is skipped by the scheduler (per policy)', function () {
    $tenant = qsTenant();
    $userId = qsSeedDueTask($tenant);
    $tenant->update(['status' => TenantStatus::Suspended->value]);

    Artisan::call('tasks:notify-due-soon');

    // The suspended tenant must not be initialized or notified.
    $count = $tenant->run(fn () => User::find($userId)->notifications()->count());
    expect($count)->toBe(0);
});

test('a retried (or overlapping) reminder run does not double-notify', function () {
    $tenant = qsTenant();
    $userId = qsSeedDueTask($tenant);

    Artisan::call('tasks:notify-due-soon');
    Artisan::call('tasks:notify-due-soon'); // simulate a retry / overlapping run

    // Deterministic in-app id keyed on the day's correlation id → idempotent.
    $count = $tenant->run(fn () => User::find($userId)->notifications()->count());
    expect($count)->toBe(1);
});

test('a failing tenant does not stop the others from processing', function () {
    $alpha = qsTenant('alpha');
    $beta = qsTenant('beta');

    // Throw for alpha; beta must still be processed. Order is not guaranteed, so
    // the assertion is symmetric: exactly one processed, exactly one failed, and
    // beta's side effect landed.
    $result = app(ActiveTenants::class)->each(function (Tenant $t) use ($alpha): void {
        if ($t->id === $alpha->id) {
            throw new RuntimeException('boom');
        }

        Customer::create(['company_name' => 'Made it', 'status' => 'active']);
    });

    expect($result['failed'])->toBe(1)
        ->and($result['processed'])->toBe(1)
        ->and($beta->run(fn () => Customer::where('company_name', 'Made it')->exists()))->toBeTrue()
        ->and($alpha->run(fn () => Customer::where('company_name', 'Made it')->exists()))->toBeFalse();
});
