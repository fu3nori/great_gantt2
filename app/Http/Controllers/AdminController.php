<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->is_system_admin, 403);

        return view('admin.index', [
            'organizations' => Organization::withCount(['members', 'projects'])->latest()->get(),
            'users' => User::latest()->limit(20)->get(), 'projectCount' => Project::withTrashed()->count(),
            'taskCount' => Task::withTrashed()->count(), 'logs' => ActivityLog::latest()->limit(30)->get(),
            'deletedProjects' => Project::onlyTrashed()->latest('deleted_at')->limit(20)->get(),
            'deletedTasks' => Task::onlyTrashed()->latest('deleted_at')->limit(20)->get(),
        ]);
    }

    public function organizationStatus(Request $request, Organization $organization, AuditLogger $audit)
    {
        abort_unless($request->user()->is_system_admin, 403);
        $before = $organization->status;
        $after = $before === 'active' ? 'suspended' : 'active';

        DB::transaction(function () use ($organization, $audit, $before, $after) {
            $organization->update(['status' => $after]);
            $audit->record(
                $organization,
                $after === 'suspended' ? 'organization.suspended' : 'organization.reactivated',
                ['status' => $before],
                ['status' => $after],
            );
        });

        return back()->with('success', $after === 'suspended' ? '事業者を停止しました。' : '事業者を再開しました。');
    }

    public function userStatus(Request $request, User $user)
    {
        abort_unless($request->user()->is_system_admin, 403);
        $user->update(['status' => $user->status === 'active' ? 'suspended' : 'active']);

        return back();
    }

    public function restore(Request $request, string $type, int $id)
    {
        abort_unless($request->user()->is_system_admin, 403);
        $model = $this->trashedModel($type, $id);
        $model->restore();

        return back()->with('success', '削除済みデータを復元しました。');
    }

    public function forceDelete(Request $request, string $type, int $id)
    {
        abort_unless($request->user()->is_system_admin, 403);
        $this->trashedModel($type, $id)->forceDelete();

        return back()->with('success', 'データを完全削除しました。');
    }

    private function trashedModel(string $type, int $id): Project|Task
    {
        $class = match ($type) {
            'project' => Project::class,
            'task' => Task::class,
            default => abort(404),
        };

        return $class::onlyTrashed()->findOrFail($id);
    }
}
