<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $this->owner($user, $project) || $this->member($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->organizationMemberships()->where('status', 'active')->whereIn('role', ['owner', 'pm'])->exists();
    }

    public function update(User $user, Project $project): bool
    {
        return $this->owner($user, $project) || $this->pm($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function inviteMember(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function invitePm(User $user, Project $project): bool
    {
        return $this->owner($user, $project);
    }

    private function owner(User $user, Project $project): bool
    {
        return $user->organizationMemberships()->where('organization_id', $project->organization_id)->where('role', 'owner')->where('status', 'active')->exists();
    }

    private function pm(User $user, Project $project): bool
    {
        return $user->projectMemberships()->where('project_id', $project->id)->where('role', 'pm')->where('status', 'active')->exists();
    }

    private function member(User $user, Project $project): bool
    {
        return $user->projectMemberships()->where('project_id', $project->id)->where('status', 'active')->exists();
    }
}
