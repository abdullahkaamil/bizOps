<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Domain\CRM\Models\Customer;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Models\Job;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Settings\TenantSettings;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Board;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskStatusHistory;
use App\Domain\Workshop\Enums\WorkshopStatus;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Builds a role-aware dashboard payload. Each widget is included only when the
 * viewer has the governing permission (and, for external reps, is scoped to their
 * own boards) — so a dashboard never leaks data the user may not see.
 */
class DashboardData
{
    public function __construct(private TenantSettings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        // Central admins (no tenant context) get no operational widgets.
        if (! tenancy()->initialized) {
            return ['scope' => 'central', 'widgets' => [], 'stats' => [], 'charts' => []];
        }

        if ($user->isExternal()) {
            return ['scope' => 'external', 'widgets' => $this->externalWidgets($user), 'stats' => [], 'charts' => $this->externalCharts($user)];
        }

        return [
            'scope' => 'internal',
            'widgets' => $this->internalWidgets($user),
            'stats' => $this->internalStats($user),
            'charts' => $this->internalCharts($user),
        ];
    }

    // --- Stat tiles & charts (colourful dashboard summary) -------------------

    /**
     * Big coloured headline numbers, each gated by the governing permission.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function internalStats(User $user): array
    {
        return array_values(array_filter([
            $user->can(Permission::ViewCustomers->value)
                ? ['label' => __('Customers'), 'value' => Customer::count(), 'color' => 'blue', 'link' => '/customers'] : null,
            $user->can(Permission::ViewJobs->value)
                ? ['label' => __('Open jobs'), 'value' => Job::whereIn('status', [JobStatus::Pending->value, JobStatus::InProgress->value])->count(), 'color' => 'green', 'link' => '/jobs'] : null,
            $user->can(Permission::ViewWorkshop->value)
                ? ['label' => __('In the workshop'), 'value' => WorkshopTicket::where('status', WorkshopStatus::InProgress->value)->count(), 'color' => 'violet', 'link' => '/workshop'] : null,
            $user->can(Permission::ViewQuotations->value)
                ? ['label' => __('Awaiting response'), 'value' => Quotation::where('status', QuotationStatus::Sent->value)->count(), 'color' => 'yellow', 'link' => '/quotations'] : null,
        ]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function internalCharts(User $user): array
    {
        $charts = [];

        if ($user->can(Permission::ViewJobs->value)) {
            $charts[] = $this->donut(__('Jobs by status'), $this->statusCounts(Job::query()), [
                JobStatus::Pending->value => [JobStatus::Pending->label(), 'yellow'],
                JobStatus::InProgress->value => [JobStatus::InProgress->label(), 'blue'],
                JobStatus::Completed->value => [JobStatus::Completed->label(), 'green'],
                JobStatus::Canceled->value => [JobStatus::Canceled->label(), 'red'],
            ]);

            $charts[] = $this->monthlyBars(__('Jobs opened (last 6 months)'), Job::query());
        }

        if ($user->can(Permission::ViewQuotations->value)) {
            $charts[] = $this->donut(__('Quotations by status'), $this->statusCounts(Quotation::query()), [
                QuotationStatus::Draft->value => [QuotationStatus::Draft->label(), 'yellow'],
                QuotationStatus::Sent->value => [QuotationStatus::Sent->label(), 'blue'],
                QuotationStatus::Accepted->value => [QuotationStatus::Accepted->label(), 'green'],
                QuotationStatus::Rejected->value => [QuotationStatus::Rejected->label(), 'red'],
                QuotationStatus::Expired->value => [QuotationStatus::Expired->label(), 'violet'],
                QuotationStatus::Canceled->value => [QuotationStatus::Canceled->label(), 'red'],
            ]);
        }

        if ($user->can(Permission::ViewTasks->value)) {
            $charts[] = $this->donut(__('Tasks by status'), $this->statusCounts(Task::query()), [
                TaskStatus::Todo->value => [TaskStatus::Todo->label(), 'yellow'],
                TaskStatus::InProgress->value => [TaskStatus::InProgress->label(), 'blue'],
                TaskStatus::Review->value => [TaskStatus::Review->label(), 'violet'],
                TaskStatus::Completed->value => [TaskStatus::Completed->label(), 'green'],
            ]);
        }

        return array_values(array_filter($charts, fn (array $c): bool => $c['total'] > 0 || ($c['series'] ?? null) !== null));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function externalCharts(User $user): array
    {
        $boardIds = Board::query()->where('type', 'project')
            ->whereHas('members', fn (Builder $q) => $q->whereKey($user->id))
            ->pluck('id');

        $donut = $this->donut(__('Your tasks by status'), $this->statusCounts(Task::query()->whereIn('board_id', $boardIds)), [
            TaskStatus::Todo->value => [TaskStatus::Todo->label(), 'yellow'],
            TaskStatus::InProgress->value => [TaskStatus::InProgress->label(), 'blue'],
            TaskStatus::Review->value => [TaskStatus::Review->label(), 'violet'],
            TaskStatus::Completed->value => [TaskStatus::Completed->label(), 'green'],
        ]);

        return $donut['total'] > 0 ? [$donut] : [];
    }

    /**
     * Count rows grouped by their `status` column.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Collection<string, int>
     */
    protected function statusCounts(Builder $query): Collection
    {
        return $query->getModel()->newQuery()
            ->selectRaw('status, count(*) as c')->groupBy('status')
            ->pluck('c', 'status')
            ->map(fn ($c): int => (int) $c);
    }

    /**
     * @param  Collection<string, int>  $counts
     * @param  array<string, array{0: string, 1: string}>  $map  status => [label, colour]
     * @return array<string, mixed>
     */
    protected function donut(string $label, Collection $counts, array $map): array
    {
        $segments = [];

        foreach ($map as $status => [$statusLabel, $color]) {
            $value = (int) ($counts[$status] ?? 0);
            if ($value > 0) {
                $segments[] = ['label' => $statusLabel, 'value' => $value, 'color' => $color];
            }
        }

        return ['type' => 'donut', 'label' => $label, 'total' => array_sum(array_column($segments, 'value')), 'segments' => $segments];
    }

    /**
     * Last 6 months of a model's row counts, as a bar series (Turkish month labels).
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return array<string, mixed>
     */
    protected function monthlyBars(string $label, Builder $query): array
    {
        $rows = $query->getModel()->newQuery()
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw("to_char(created_at, 'YYYY-MM') as ym, count(*) as c")
            ->groupBy('ym')->pluck('c', 'ym');

        $months = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
        $series = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $series[] = ['label' => $months[$month->month - 1], 'value' => (int) ($rows[$month->format('Y-m')] ?? 0)];
        }

        return ['type' => 'bars', 'label' => $label, 'color' => 'blue', 'total' => array_sum(array_column($series, 'value')), 'series' => $series];
    }

    /**
     * @return array<string, mixed>
     */
    protected function externalWidgets(User $user): array
    {
        $boardIds = Board::query()->where('type', 'project')
            ->whereHas('members', fn (Builder $q) => $q->whereKey($user->id))
            ->pluck('id');

        return array_filter([
            'assigned_boards' => $this->widget(__('Your boards'),
                Board::query()->whereIn('id', $boardIds)->where('is_active', true)->withCount('tasks')->get()
                    ->map(fn (Board $b): array => ['title' => $b->name, 'meta' => $b->tasks_count.' '.__('tasks'), 'link' => "/boards/{$b->public_id}"])),
            'tasks_awaiting_approval' => $this->widget(__('Awaiting your approval'),
                Task::query()->whereIn('board_id', $boardIds)->where('status', TaskStatus::Review->value)->with('board')->latest('id')->limit(8)->get()
                    ->map(fn (Task $t): array => ['title' => $t->title, 'meta' => $t->board->name, 'link' => "/boards/{$t->board->public_id}?task={$t->public_id}"])),
            'recent_task_updates' => $this->widget(__('Recent updates'),
                TaskStatusHistory::query()->whereHas('task', fn (Builder $q) => $q->whereIn('board_id', $boardIds))
                    ->with('task')->latest('id')->limit(8)->get()
                    ->map(fn (TaskStatusHistory $h): array => ['title' => $h->task !== null ? $h->task->title : __('Task'), 'meta' => $h->to_status->label(), 'link' => null])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function internalWidgets(User $user): array
    {
        $soon = now()->addHours(48);

        return array_filter([
            'my_jobs' => $user->can(Permission::ViewJobs->value) ? $this->widget(__('Your jobs'),
                Job::query()->where('assigned_user_id', $user->id)
                    ->whereIn('status', [JobStatus::Pending->value, JobStatus::InProgress->value])
                    ->orderBy('planned_at')->limit(6)->get()
                    ->map(fn (Job $j): array => ['title' => $j->title, 'meta' => $j->status->label(), 'link' => "/jobs/{$j->public_id}"])) : null,

            'jobs_today' => $user->can(Permission::AssignJobs->value) ? $this->widget(__('Scheduled today'),
                Job::query()->whereDate('planned_at', now()->toDateString())
                    ->with('assignee')->orderBy('planned_at')->limit(8)->get()
                    ->map(fn (Job $j): array => ['title' => $j->title, 'meta' => $j->assignee !== null ? $j->assignee->name : __('Unassigned'), 'link' => "/jobs/{$j->public_id}"])) : null,

            'jobs_in_progress' => $user->can(Permission::AssignJobs->value)
                ? $this->countWidget('Jobs in progress', Job::query()->where('status', JobStatus::InProgress->value)->count()) : null,

            'my_tasks' => $user->can(Permission::ViewTasks->value) ? $this->widget(__('Your tasks'),
                Task::query()->whereHas('assignees', fn (Builder $q) => $q->whereKey($user->id))
                    ->where('status', '!=', TaskStatus::Completed->value)->with('board')->latest('id')->limit(6)->get()
                    ->map(fn (Task $t): array => ['title' => $t->title, 'meta' => $t->status->label(), 'link' => "/boards/{$t->board->public_id}?task={$t->public_id}"])) : null,

            'tasks_due_soon' => $user->can(Permission::ViewTasks->value) ? $this->widget(__('Tasks due soon'),
                Task::query()->whereHas('assignees', fn (Builder $q) => $q->whereKey($user->id))
                    ->whereNotNull('due_at')->whereBetween('due_at', [now(), $soon])
                    ->where('status', '!=', TaskStatus::Completed->value)->with('board')->limit(6)->get()
                    ->map(fn (Task $t): array => ['title' => $t->title, 'meta' => __('due').' '.$t->due_at?->diffForHumans(), 'link' => "/boards/{$t->board->public_id}?task={$t->public_id}"])) : null,

            'review_queue' => $user->can(Permission::SubmitTaskReview->value) ? $this->widget(__('In review'),
                Task::query()->where('status', TaskStatus::Review->value)
                    ->whereHas('board', fn (Builder $q) => $q->whereHas('members', fn (Builder $m) => $m->whereKey($user->id)))
                    ->with('board')->latest('id')->limit(6)->get()
                    ->map(fn (Task $t): array => ['title' => $t->title, 'meta' => $t->board->name, 'link' => "/boards/{$t->board->public_id}?task={$t->public_id}"])) : null,

            'active_workshop' => $user->can(Permission::ViewWorkshop->value) ? $this->widget(__('Workshop in progress'),
                WorkshopTicket::query()->where('status', WorkshopStatus::InProgress->value)->with('device')->latest('id')->limit(6)->get()
                    ->map(fn (WorkshopTicket $t): array => ['title' => $t->device ? trim($t->device->brand.' '.$t->device->model) : $t->number, 'meta' => $t->number, 'link' => "/workshop/{$t->public_id}"])) : null,

            'workshop_waiting_delivery' => $user->can(Permission::DeliverWorkshop->value) ? $this->widget(__('Waiting for delivery'),
                WorkshopTicket::query()->where('status', WorkshopStatus::Completed->value)->with('device')->latest('id')->limit(6)->get()
                    ->map(fn (WorkshopTicket $t): array => ['title' => $t->device ? trim($t->device->brand.' '.$t->device->model) : $t->number, 'meta' => $t->number, 'link' => "/workshop/{$t->public_id}"])) : null,

            'low_stock' => $user->can(Permission::ManageInventory->value) ? $this->widget(__('Low stock'),
                InventoryItem::query()->withSum('balances as stock', 'quantity')
                    ->get()->filter(fn (InventoryItem $i): bool => (float) ($i->stock ?? 0) <= $this->settings->inventoryLowStockThreshold())
                    ->take(8)->values()
                    ->map(fn (InventoryItem $i): array => ['title' => $i->name, 'meta' => ((float) ($i->stock ?? 0)).' '.$i->unit, 'link' => "/inventory/{$i->public_id}"])) : null,

            'draft_quotations' => $user->can(Permission::ViewQuotations->value) ? $this->widget(__('Draft quotations'),
                Quotation::query()->where('status', QuotationStatus::Draft->value)->with('customer')->latest('id')->limit(6)->get()
                    ->map(fn (Quotation $q): array => ['title' => $q->number, 'meta' => $q->customer !== null ? $q->customer->company_name : '', 'link' => "/quotations/{$q->public_id}"])) : null,

            'recently_completed' => ($user->can(Permission::ViewJobs->value) || $user->can(Permission::ViewWorkshop->value)) ? $this->widget(__('Recently completed'),
                Job::query()->where('status', JobStatus::Completed->value)->whereNotNull('actual_end_at')
                    ->latest('actual_end_at')->limit(6)->get()
                    ->map(fn (Job $j): array => ['title' => $j->title, 'meta' => $j->actual_end_at?->diffForHumans() ?? '', 'link' => "/jobs/{$j->public_id}"])) : null,

            'recent_activity' => $user->can(Permission::ViewActivity->value) ? $this->widget(__('Recent activity'),
                Activity::query()->latest()->limit(10)->get()
                    ->map(fn (Activity $a): array => ['title' => (string) $a->description, 'meta' => $a->created_at?->diffForHumans() ?? '', 'link' => null])) : null,
        ]);
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function widget(string $label, iterable $items): array
    {
        $items = collect($items);

        return ['label' => $label, 'count' => $items->count(), 'items' => $items->all()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function countWidget(string $label, int $count): array
    {
        return ['label' => $label, 'count' => $count, 'items' => []];
    }
}
