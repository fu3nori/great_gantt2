<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public Project $project, public string $action = 'updated') {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('project.'.$this->project->id)];
    }

    public function broadcastAs(): string
    {
        return 'ProjectUpdated';
    }

    public function broadcastWith(): array
    {
        return ['project' => $this->project->toArray(), 'action' => $this->action];
    }
}
