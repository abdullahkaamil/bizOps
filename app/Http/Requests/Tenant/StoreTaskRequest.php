<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\Tasks\Enums\Priority;
use App\Domain\Tasks\Models\Board;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $board = $this->route('board');
        $activeMemberIds = $board instanceof Board
            ? $board->members()->where('status', 'active')->pluck('users.id')->all()
            : [];

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['nullable', Rule::in(Priority::values())],
            'due_at' => ['nullable', 'date'],
            'assignee_ids' => ['array'],
            'assignee_ids.*' => [
                'distinct',
                Rule::exists((new User)->getTable(), 'public_id')->whereIn('id', $activeMemberIds),
            ],
        ];
    }
}
