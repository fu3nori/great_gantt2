<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Notifications\TaskCommentPostedNotification;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TaskCommentController extends Controller
{
    public function store(Request $request, Project $project, Task $task, AuditLogger $audit)
    {
        abort_unless($task->project_id === $project->id, 404);
        $this->authorize('comment', $task);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'parent_id' => ['nullable', 'integer', 'exists:task_comments,id']]);
        $comment = DB::transaction(function () use ($task, $request, $data, $audit) {
            $comment = $task->comments()->create(['user_id' => $request->user()->id, 'parent_id' => $data['parent_id'] ?? null, 'body' => $data['body']]);
            $audit->record($comment, 'comment.created');

            return $comment->load(['user', 'task.project']);
        });
        $recipients = $project->members()->where('status', 'active')->where('user_id', '!=', $request->user()->id)->with('user')->get()->pluck('user')->filter()->unique('id');
        Notification::send($recipients, new TaskCommentPostedNotification($comment));

        return back()->with('success', 'コメントを投稿しました。');
    }
}
