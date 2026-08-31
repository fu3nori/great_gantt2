<?php

namespace App\Http\Controllers;

use App\Actions\Projects\CreateProjectAction;
use App\Events\ProjectUpdated;
use App\Http\Requests\ProjectRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function create(Request $request)
    {
        $this->authorize('create', Project::class);
        $organization = $request->user()->organizationMemberships()->where('status', 'active')->with('organization')->firstOrFail()->organization;

        return view('projects.create', ['organization' => $organization, 'pmCandidates' => $this->pmCandidates($organization), 'project' => new Project]);
    }

    public function store(ProjectRequest $request, CreateProjectAction $action)
    {
        $this->authorize('create', Project::class);
        $organization = $request->user()->organizationMemberships()->where('status', 'active')->with('organization')->firstOrFail()->organization;
        $project = $action->execute($organization, $request->user(), $request->validated());

        return redirect()->route('projects.show', $project)->with('success', 'プロジェクトを作成しました。');
    }

    public function show(Project $project)
    {
        $this->authorize('view', $project);
        $project->load(['members.user', 'tasks.assignee', 'invitations' => fn ($query) => $query->whereNull('accepted_at')->latest()]);

        return view('projects.show', compact('project'));
    }

    public function edit(Project $project)
    {
        $this->authorize('update', $project);

        return view('projects.edit', ['project' => $project->load('members'), 'organization' => $project->organization, 'pmCandidates' => $this->pmCandidates($project->organization)]);
    }

    public function update(ProjectRequest $request, Project $project, AuditLogger $audit)
    {
        $this->authorize('update', $project);
        DB::transaction(function () use ($request, $project, $audit) {
            $before = $project->only(['name', 'description', 'start_date', 'end_date']);
            $project->update($request->safe()->except('pm_user_ids'));
            if ($request->has('pm_user_ids')) {
                $requestedIds = collect($request->validated('pm_user_ids', []))->unique();
                $ids = $project->organization->members()->where('status', 'active')->whereIn('role', ['owner', 'pm'])->whereIn('user_id', $requestedIds)->pluck('user_id');
                $project->members()->where('role', 'pm')->whereNotIn('user_id', $ids)->delete();
                foreach ($ids as $id) {
                    $project->members()->updateOrCreate(['user_id' => $id], ['role' => 'pm', 'status' => 'active', 'joined_at' => now()]);
                }
            }
            $audit->record($project, 'project.updated', $before, $project->fresh()->only(array_keys($before)));
            ProjectUpdated::dispatch($project->fresh());
        });

        return redirect()->route('projects.show', $project)->with('success', 'プロジェクトを更新しました。');
    }

    public function destroy(Project $project, AuditLogger $audit)
    {
        $this->authorize('delete', $project);
        $audit->record($project, 'project.deleted');
        $project->delete();
        ProjectUpdated::dispatch($project, 'deleted');

        return redirect()->route('home')->with('success', 'プロジェクトを削除しました。');
    }

    private function pmCandidates(Organization $organization)
    {
        return $organization->members()->where('status', 'active')->whereIn('role', ['owner', 'pm'])->with('user')->get()->pluck('user');
    }
}
