<?php

namespace App\Actions\Projects;

use App\Events\ProjectUpdated;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CreateProjectAction
{
    public function __construct(private AuditLogger $audit) {}

    public function execute(Organization $organization, User $actor, array $data): Project
    {
        $pmIds = collect($data['pm_user_ids'] ?? []);
        $actorRole = $actor->organizationMemberships()->where('organization_id', $organization->id)->value('role');
        if ($actorRole === 'pm') {
            $pmIds->push($actor->id);
        }
        $pmIds = $pmIds->unique();

        return DB::transaction(function () use ($organization, $actor, $data, $pmIds) {
            $project = Project::create([
                'organization_id' => $organization->id, 'name' => $data['name'], 'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'status' => 'active', 'created_by' => $actor->id,
            ]);
            $allowed = $organization->members()->where('status', 'active')->whereIn('user_id', $pmIds)->pluck('user_id');
            foreach ($allowed as $userId) {
                $project->members()->create(['user_id' => $userId, 'role' => 'pm', 'status' => 'active', 'joined_at' => now()]);
            }
            $this->audit->record($project, 'project.created');
            ProjectUpdated::dispatch($project, 'created');

            return $project;
        });
    }
}
