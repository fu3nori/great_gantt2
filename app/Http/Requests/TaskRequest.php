<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimes = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'title' => [$sometimes, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => [$sometimes, 'date'],
            'end_date' => [$sometimes, 'date'],
            'progress' => [$sometimes, 'integer', 'min:0', 'max:100'],
            'status' => [$sometimes, Rule::enum(TaskStatus::class)],
            'sort_order' => ['nullable', 'integer'],
            'lock_version' => [$this->isMethod('PATCH') ? 'required' : 'nullable', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $project = $this->route('project');
            if (! $project instanceof Project) {
                return;
            }
            $assignee = $this->input('assignee_id');
            if ($assignee && ! $project->members()->where('user_id', $assignee)->where('status', 'active')->exists()) {
                $validator->errors()->add('assignee_id', '担当者はプロジェクト参加者から選択してください。');
            }
            $parentId = $this->input('parent_id');
            if ($parentId && ! $project->tasks()->whereKey($parentId)->exists()) {
                $validator->errors()->add('parent_id', '親タスクは同じプロジェクトから選択してください。');
            }
            $start = $this->input('start_date', $this->route('task')?->start_date?->toDateString());
            $end = $this->input('end_date', $this->route('task')?->end_date?->toDateString());
            if ($start && $end && $end < $start) {
                $validator->errors()->add('end_date', '終了日は開始日以降にしてください。');
            }
        }];
    }
}
