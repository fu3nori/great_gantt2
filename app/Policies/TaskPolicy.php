<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $user->can('view', $task->project);
    }

    public function create(User $user, $project): bool
    {
        return $user->can('view', $project);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can('update', $task->project);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }
}
