<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Policies;

use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Models\Job;
use App\Enums\Permission;
use App\Models\User;

/**
 * Authorizes field-service jobs. Jobs are an internal-only module — external
 * representatives never reach them (also enforced by the `internal` route
 * middleware). Field actions (start/complete, adding notes/photos/signature) are
 * limited to the assigned technician, while managers with the assign permission
 * may act on any job.
 */
class JobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isInternal() && $user->can(Permission::ViewJobs->value);
    }

    public function view(User $user, Job $job): bool
    {
        return $user->isInternal() && $user->can(Permission::ViewJobs->value);
    }

    public function create(User $user): bool
    {
        return $user->isInternal() && $user->can(Permission::CreateJobs->value);
    }

    public function update(User $user, Job $job): bool
    {
        return $user->isInternal() && $user->can(Permission::CreateJobs->value) && ! $job->status->isTerminal();
    }

    public function assign(User $user, Job $job): bool
    {
        return $user->isInternal() && $user->can(Permission::AssignJobs->value);
    }

    public function start(User $user, Job $job): bool
    {
        return $job->status === JobStatus::Pending
            && $user->can(Permission::StartJobs->value)
            && $this->worksOn($user, $job);
    }

    public function complete(User $user, Job $job): bool
    {
        return $job->status === JobStatus::InProgress
            && $user->can(Permission::CompleteJobs->value)
            && $this->worksOn($user, $job);
    }

    public function cancel(User $user, Job $job): bool
    {
        return ! $job->status->isTerminal()
            && $user->isInternal()
            && $user->can(Permission::CancelJobs->value);
    }

    public function reopen(User $user, Job $job): bool
    {
        // A privileged override, gated behind the cancel permission (managers+).
        return $job->status->isTerminal()
            && $user->isInternal()
            && $user->can(Permission::CancelJobs->value);
    }

    /**
     * On-site service data (notes, photos, signature) — the assigned technician,
     * or a manager, while the job is still open.
     */
    public function updateServiceData(User $user, Job $job): bool
    {
        return $user->can(Permission::ViewJobs->value)
            && $job->status === JobStatus::InProgress
            && $this->worksOn($user, $job);
    }

    public function generateReport(User $user, Job $job): bool
    {
        return $job->status === JobStatus::Completed && $this->view($user, $job);
    }

    /**
     * The assigned technician, or anyone able to assign jobs (managers+).
     */
    protected function worksOn(User $user, Job $job): bool
    {
        return $user->isInternal()
            && ($job->assigned_user_id === $user->id || $user->can(Permission::AssignJobs->value));
    }
}
