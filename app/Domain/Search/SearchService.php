<?php

declare(strict_types=1);

namespace App\Domain\Search;

use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\CustomerContact;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Jobs\Models\Job;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Tasks\Models\Board;
use App\Domain\Tasks\Models\Task;
use App\Domain\Workshop\Models\CustomerDevice;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tenant-scoped global search. Authorization happens at the QUERY level: a result
 * type is only searched when the viewer may see it (and rows are pre-scoped, e.g.
 * tasks to accessible boards) — forbidden records are never fetched, then hidden.
 * PostgreSQL ILIKE (trigram-indexed) backs the matching.
 */
class SearchService
{
    private const LIMIT = 8;

    /**
     * @return array<int, array{type: string, label: string, results: array<int, array<string, mixed>>}>
     */
    public function search(User $user, string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';

        $groups = [];

        if ($user->can(Permission::ViewCustomers->value)) {
            $groups[] = $this->group('customers', __('Customers'), $this->customers($like));
            $groups[] = $this->group('contacts', __('Contacts'), $this->contacts($like));
        }

        if ($user->can(Permission::ViewJobs->value)) {
            $groups[] = $this->group('jobs', __('Jobs'), $this->jobs($like));
        }

        if ($user->can(Permission::ViewWorkshop->value)) {
            $groups[] = $this->group('devices', __('Devices'), $this->devices($like));
            $groups[] = $this->group('workshop', __('Workshop tickets'), $this->workshop($like));
        }

        [$boardScoped, $boardIds] = $this->boardScope($user);
        if ($boardScoped) {
            $groups[] = $this->group('boards', __('Boards'), $this->boards($like, $boardIds));
            $groups[] = $this->group('tasks', __('Tasks'), $this->tasks($like, $boardIds));
        }

        if ($user->can(Permission::ViewQuotations->value)) {
            $groups[] = $this->group('quotations', __('Quotations'), $this->quotations($like));
        }

        if ($user->can(Permission::ViewInventory->value)) {
            $groups[] = $this->group('inventory', __('Inventory'), $this->inventory($like));
        }

        // Only return groups that actually matched.
        return array_values(array_filter($groups, fn (array $g): bool => $g['results'] !== []));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function customers(string $like): array
    {
        return Customer::query()
            ->where(fn (Builder $q) => $q->where('company_name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)->orWhere('phone', 'ilike', $like))
            ->limit(self::LIMIT)->get()
            ->map(fn (Customer $c): array => $this->row($c->public_id, $c->company_name, $c->email, "/customers/{$c->public_id}"))->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contacts(string $like): array
    {
        return CustomerContact::query()
            ->where(fn (Builder $q) => $q->where('first_name', 'ilike', $like)
                ->orWhere('last_name', 'ilike', $like)->orWhere('email', 'ilike', $like))
            ->with('customer')->limit(self::LIMIT)->get()
            ->map(fn (CustomerContact $c): array => $this->row(
                $c->public_id, trim($c->first_name.' '.$c->last_name), $c->customer?->company_name,
                $c->customer ? "/customers/{$c->customer->public_id}" : null,
            ))->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function jobs(string $like): array
    {
        return Job::query()
            ->where(fn (Builder $q) => $q->where('number', 'ilike', $like)->orWhere('title', 'ilike', $like))
            ->limit(self::LIMIT)->get()
            ->map(fn (Job $j): array => $this->row($j->public_id, $j->title, $j->number, "/jobs/{$j->public_id}"))->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function devices(string $like): array
    {
        return CustomerDevice::query()
            ->where(fn (Builder $q) => $q->where('serial_number', 'ilike', $like)
                ->orWhere('brand', 'ilike', $like)->orWhere('model', 'ilike', $like))
            ->limit(self::LIMIT)->get()
            ->map(fn (CustomerDevice $d): array => $this->row(
                $d->public_id, trim($d->brand.' '.$d->model), $d->serial_number ? "SN {$d->serial_number}" : null, null,
            ))->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workshop(string $like): array
    {
        return WorkshopTicket::query()
            ->where(fn (Builder $q) => $q->where('number', 'ilike', $like)->orWhere('issue_description', 'ilike', $like))
            ->limit(self::LIMIT)->get()
            ->map(fn (WorkshopTicket $t): array => $this->row($t->public_id, $t->number, $t->status->label(), "/workshop/{$t->public_id}"))->all();
    }

    /**
     * @param  Collection<int, int>|null  $boardIds
     * @return array<int, array<string, mixed>>
     */
    private function boards(string $like, $boardIds): array
    {
        return Board::query()
            ->when($boardIds !== null, fn (Builder $q) => $q->whereIn('id', $boardIds))
            ->where('name', 'ilike', $like)
            ->limit(self::LIMIT)->get()
            ->map(fn (Board $b): array => $this->row($b->public_id, $b->name, $b->type->label(), "/boards/{$b->public_id}"))->all();
    }

    /**
     * @param  Collection<int, int>|null  $boardIds
     * @return array<int, array<string, mixed>>
     */
    private function tasks(string $like, $boardIds): array
    {
        return Task::query()
            ->when($boardIds !== null, fn (Builder $q) => $q->whereIn('board_id', $boardIds))
            ->where('title', 'ilike', $like)
            ->with('board')->limit(self::LIMIT)->get()
            ->map(fn (Task $t): array => $this->row(
                $t->public_id, $t->title, $t->board->name, "/boards/{$t->board->public_id}?task={$t->public_id}",
            ))->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function quotations(string $like): array
    {
        return Quotation::query()
            ->where('number', 'ilike', $like)
            ->with('customer')->limit(self::LIMIT)->get()
            ->map(fn (Quotation $q): array => $this->row($q->public_id, $q->number, $q->customer?->company_name, "/quotations/{$q->public_id}"))->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function inventory(string $like): array
    {
        return InventoryItem::query()
            ->where(fn (Builder $q) => $q->where('sku', 'ilike', $like)->orWhere('name', 'ilike', $like))
            ->limit(self::LIMIT)->get()
            ->map(fn (InventoryItem $i): array => $this->row($i->public_id, $i->name, $i->sku, "/inventory/{$i->public_id}"))->all();
    }

    /**
     * Which boards this user may search: all (internal with tasks.view) → null (no
     * restriction); their member boards (external); or no access.
     *
     * @return array{0: bool, 1: Collection<int, int>|null}
     */
    private function boardScope(User $user): array
    {
        if ($user->isInternal() && $user->can(Permission::ViewTasks->value)) {
            return [true, null];
        }

        if ($user->isExternal()) {
            $ids = Board::query()->where('type', 'project')
                ->whereHas('members', fn (Builder $q) => $q->whereKey($user->id))->pluck('id');

            return [true, $ids];
        }

        return [false, null];
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array{type: string, label: string, results: array<int, array<string, mixed>>}
     */
    private function group(string $type, string $label, array $results): array
    {
        return ['type' => $type, 'label' => $label, 'results' => $results];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $id, string $title, ?string $subtitle, ?string $link): array
    {
        return ['id' => $id, 'title' => $title, 'subtitle' => $subtitle, 'link' => $link];
    }
}
