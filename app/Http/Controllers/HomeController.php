<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $query = Project::query()->with(['members.user', 'tasks'])->latest();
        if (! $user->is_system_admin) {
            $ownedOrganizations = $user->organizationMemberships()->where('role', 'owner')->where('status', 'active')->pluck('organization_id');
            $projectIds = $user->projectMemberships()->where('status', 'active')->pluck('project_id');
            $query->where(fn ($q) => $q->whereIn('organization_id', $ownedOrganizations)->orWhereIn('id', $projectIds));
        }

        return view('home', ['projects' => $query->get()]);
    }
}
