<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tenant-local permissions, named `resource.action`. Stored as spatie permission
 * records (guard: web) inside each tenant database. All authorization is enforced
 * server-side through policies/gates; the frontend only mirrors these for display.
 */
enum Permission: string
{
    // Users / team
    case ViewUsers = 'users.view';
    case CreateUsers = 'users.create';
    case UpdateUsers = 'users.update';
    case SuspendUsers = 'users.suspend';
    case DeleteUsers = 'users.delete';

    // Roles & activity
    case ManageRoles = 'roles.manage';
    case ViewActivity = 'activity.view';

    // Customers
    case ViewCustomers = 'customers.view';
    case CreateCustomers = 'customers.create';
    case UpdateCustomers = 'customers.update';
    case DeleteCustomers = 'customers.delete';

    // Tasks
    case ViewTasks = 'tasks.view';
    case CreateTasks = 'tasks.create';
    case UpdateTasks = 'tasks.update';
    case AssignTasks = 'tasks.assign';
    case StartTasks = 'tasks.start';
    case SubmitTaskReview = 'tasks.submit_review';
    case ApproveTasks = 'tasks.approve';
    case RejectTasks = 'tasks.reject';

    // Jobs
    case ViewJobs = 'jobs.view';
    case CreateJobs = 'jobs.create';
    case AssignJobs = 'jobs.assign';
    case StartJobs = 'jobs.start';
    case CompleteJobs = 'jobs.complete';
    case CancelJobs = 'jobs.cancel';

    // Workshop
    case ViewWorkshop = 'workshop.view';
    case CreateWorkshop = 'workshop.create';
    case UpdateWorkshop = 'workshop.update';
    case CompleteWorkshop = 'workshop.complete';
    case DeliverWorkshop = 'workshop.deliver';

    // Inventory
    case ViewInventory = 'inventory.view';
    case ManageInventory = 'inventory.manage';
    case ViewInventoryCost = 'inventory.view_cost';

    // Quotations
    case ViewQuotations = 'quotations.view';
    case CreateQuotations = 'quotations.create';
    case UpdateQuotations = 'quotations.update';
    case SendQuotations = 'quotations.send';
    case AcceptQuotations = 'quotations.accept';

    // Settings & reports
    case ViewSettings = 'settings.view';
    case UpdateSettings = 'settings.update';
    case ViewReports = 'reports.view';

    /**
     * The resource this permission belongs to (the part before the dot), e.g.
     * "tasks.view" -> "tasks". Used to group permissions in the Roles UI.
     */
    public function group(): string
    {
        return explode('.', $this->value)[0];
    }

    /**
     * The action part of the permission, e.g. "tasks.view" -> "view".
     */
    public function action(): string
    {
        return explode('.', $this->value, 2)[1] ?? $this->value;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }

    /**
     * Permissions grouped by resource, in enum order, for the Roles editor.
     *
     * @return array<int, array{group: string, permissions: array<int, array{value: string, action: string}>}>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = [
                'value' => $permission->value,
                'action' => $permission->action(),
            ];
        }

        return array_map(
            static fn (string $group, array $permissions): array => ['group' => $group, 'permissions' => $permissions],
            array_keys($groups),
            array_values($groups),
        );
    }
}
