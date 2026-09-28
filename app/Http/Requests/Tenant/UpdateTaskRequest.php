<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\Tasks\Enums\Priority;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $task = $this->route('task');
        $activeMemberIds = $task instanceof Task
            ? $task->board->members()->where('status', 'active')->pluck('users.id')->all()
            : [];

        // The task's definition (title + description) is immutable once created —
        // it is the agreed contract. Only workflow metadata is editable here;
        // later changes of intent go through comments.
        return [
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
