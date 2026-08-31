<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Events\TaskUpdated;
use App\Models\Task;
use App\Notifications\TaskProgressChangedNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class UpdateTaskAction
{
    public function __construct(private AuditLogger $audit) {}

    public function execute(Task $task, array $data): Task
    {
        $expected = (int) $data['lock_version'];
        unset($data['lock_version']);
        $updated = DB::transaction(function () use ($task, $data, $expected) {
            $locked = Task::query()->lockForUpdate()->findOrFail($task->id);
            if ($locked->lock_version !== $expected) {
                abort(response()->json(['message' => '他のユーザーが更新しました。最新データを反映します。', 'task' => $locked->load('assignee')], 409));
            }
            $fields = ['title', 'description', 'parent_id', 'assignee_id', 'start_date', 'end_date', 'progress', 'status', 'sort_order'];
            $before = $locked->only($fields);
            if (($data['status'] ?? null) === TaskStatus::Completed->value) {
                $data['progress'] = 100;
            }
            $locked->fill($data);
            $locked->lock_version++;
            $locked->save();
            $after = $locked->fresh()->only($fields);
            $this->audit->record($locked, 'task.updated', $before, $after);
            TaskUpdated::dispatch($locked->fresh());

            return [$locked->fresh(['project', 'assignee']), (int) $before['progress'], (int) $after['progress']];
        });
        [$result, $beforeProgress, $afterProgress] = $updated;
        if ($beforeProgress !== $afterProgress) {
            $recipients = $result->project->members()->where('status', 'active')->where('user_id', '!=', auth()->id())->with('user')->get()->pluck('user')->filter()->unique('id');
            Notification::send($recipients, new TaskProgressChangedNotification($result, $beforeProgress, $afterProgress, auth()->user()?->name ?? 'ユーザー'));
        }

        return $result;
    }
}
