<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Console;

use App\Domain\Notifications\Notifications\TaskDueSoonNotification;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use App\Support\Tenancy\ActiveTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Scans every *active* tenant for tasks due within the window and notifies their
 * assignees. Scheduled hourly with an overlap lock (see routes/console.php).
 *
 * Tenant-aware by construction: each tenant is initialized via ActiveTenants
 * (which skips suspended tenants and isolates per-tenant failures). Idempotency
 * comes from the deterministic in-app notification id keyed on the day's
 * correlation id, so a retried or overlapping run never double-notifies.
 */
class NotifyTasksDueSoon extends Command
{
    protected $signature = 'tasks:notify-due-soon {--hours=24}';

    protected $description = 'Notify assignees of tasks that are due soon, across all active tenants';

    public function handle(ActiveTenants $tenants): int
    {
        $hours = (int) $this->option('hours');

        $tenants->each(function () use ($hours): void {
            $tasks = Task::query()
                ->with(['assignees', 'board'])
                ->whereNotNull('due_at')
                ->whereNotIn('status', [TaskStatus::Completed->value])
                ->whereBetween('due_at', [now(), now()->addHours($hours)])
                ->get();

            foreach ($tasks as $task) {
                $recipients = $task->assignees->filter(
                    fn (User $u): bool => $u->isInternal() && $u->isActive(),
                );

                if ($recipients->isNotEmpty()) {
                    // A stable correlation id per task+day keeps repeat runs idempotent.
                    $correlation = 'due-soon:'.$task->public_id.':'.now()->toDateString();

                    Notification::send($recipients, new TaskDueSoonNotification(
                        $task->id,
                        $task->title,
                        $task->public_id,
                        $task->board->public_id,
                        $task->due_at?->toIso8601String(),
                        $correlation,
                    ));
                }
            }
        });

        return self::SUCCESS;
    }
}
