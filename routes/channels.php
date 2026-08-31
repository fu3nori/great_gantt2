<?php

use App\Models\Project;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('project.{projectId}', function ($user, $projectId) {
    if ($user->is_system_admin) {
        return true;
    }
    $project = Project::find($projectId);
    if (! $project) {
        return false;
    }

    return $user->organizationMemberships()->where('organization_id', $project->organization_id)->where('role', 'owner')->where('status', 'active')->exists()
        || $user->projectMemberships()->where('project_id', $projectId)->where('status', 'active')->exists();
});
