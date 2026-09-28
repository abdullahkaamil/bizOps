<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domain\Tasks\Enums\CommentVisibility;
use App\Domain\Tasks\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskCommentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $task = $this->route('task');
        $isProjectBoard = $task instanceof Task && $task->board->isProject();
        $isInternalBoard = $task instanceof Task && $task->board->isInternal();

        return [
            'body' => ['required', 'string', 'max:10000'],
            'visibility' => [
                Rule::requiredIf($isProjectBoard),
                Rule::prohibitedIf($isInternalBoard),
                Rule::in(CommentVisibility::values()),
            ],
        ];
    }
}
