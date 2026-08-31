<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Http\Request;

class WbsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $query = Project::query()->with(['members.user', 'tasks' => fn ($q) => $q->with('assignee')->orderBy('sort_order')->orderBy('id')])->orderBy('name');
        if (! $user->is_system_admin) {
            $owned = $user->organizationMemberships()->where('role', 'owner')->where('status', 'active')->pluck('organization_id');
            $ids = $user->projectMemberships()->where('status', 'active')->pluck('project_id');
            $query->where(fn ($q) => $q->whereIn('organization_id', $owned)->orWhereIn('id', $ids));
        }
        $projects = $query->get();
        $tasks = $projects->flatMap(fn (Project $project) => $project->tasks);
        $starts = $projects->pluck('start_date')->merge($tasks->pluck('start_date'))->push(now()->startOfDay())->filter();
        $ends = $projects->pluck('end_date')->merge($tasks->pluck('end_date'))->push(now()->startOfDay())->filter();
        $min = $starts->sortBy(fn ($date) => $date->timestamp)->first()?->copy()->subDays(7) ?? now()->startOfMonth();
        $max = $ends->sortByDesc(fn ($date) => $date->timestamp)->first()?->copy()->addDays(30) ?? now()->addMonths(4);
        if ($max->diffInDays($min) < 119) {
            $max = $min->copy()->addDays(119);
        }
        $dates = collect();
        for ($date = $min->copy(); $date->lte($max); $date->addDay()) {
            $dates->push($date->copy());
        }

        return view('wbs.index', compact('projects', 'dates') + ['statuses' => TaskStatus::cases()]);
    }
}
