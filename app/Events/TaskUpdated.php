<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public Task $task, public string $action = 'updated') {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('project.'.$this->task->project_id)];
    }

    public function broadcastAs(): string
    {
        return 'TaskUpdated';
    }

    public function broadcastWith(): array
    {
        $task = $this->task->load('assignee');
        $projectProgress = (int) round($task->project->tasks()->avg('progress') ?? 0);

        return ['task' => $task->toArray(), 'project_progress' => $projectProgress, 'action' => $this->action];
    }
}
