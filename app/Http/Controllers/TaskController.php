<?php

namespace App\Http\Controllers;

use App\Actions\Tasks\UpdateTaskAction;
use App\Enums\TaskStatus;
use App\Events\TaskUpdated;
use App\Http\Requests\TaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function store(TaskRequest $request, Project $project, AuditLogger $audit)
    {
        $this->authorize('create', [Task::class, $project]);
        $data = $request->validated();
        unset($data['lock_version']);

        $task = DB::transaction(function () use ($data, $project, $request, $audit) {
            $task = $project->tasks()->create(array_merge($data, ['created_by' => $request->user()->id]));
            $audit->record($task, 'task.created');
            TaskUpdated::dispatch($task);

            return $task;
        });

        if ($request->expectsJson()) {
            return response()->json(['task' => $task->load('assignee')], 201);
        }

        return redirect()->route('tasks.show', [$project, $task])->with('success', 'タスクを作成しました。');
    }

    public function show(Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 404);
        $this->authorize('view', $task);
        $task->load([
            'assignee',
            'comments' => fn ($query) => $query->whereNull('parent_id')->with(['user', 'replies'])->oldest(),
            'project.members.user',
        ]);

        return view('tasks.show', [
            'project' => $project,
            'task' => $task,
            'statuses' => TaskStatus::cases(),
        ]);
    }

    public function update(TaskRequest $request, Project $project, Task $task, UpdateTaskAction $action)
    {
        abort_unless($task->project_id === $project->id, 404);
        $this->authorize('update', $task);
        $updated = $action->execute($task, $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'タスクを更新しました。',
                'task' => $updated,
                'status_label' => $updated->status->label(),
            ]);
        }

        return back()->with('success', 'タスクを更新しました。');
    }

    public function destroy(Project $project, Task $task, AuditLogger $audit)
    {
        abort_unless($task->project_id === $project->id, 404);
        $this->authorize('delete', $task);
        $audit->record($task, 'task.deleted');
        $task->delete();
        TaskUpdated::dispatch($task, 'deleted');

        return redirect()->route('projects.show', $project)->with('success', 'タスクを削除しました。');
    }
}
