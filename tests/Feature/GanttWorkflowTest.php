<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use App\Notifications\TaskCommentPostedNotification;
use App\Notifications\TaskProgressChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GanttWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function initializeSystem(): User
    {
        return User::factory()->create(['is_system_admin' => true]);
    }

    private function workspace(): array
    {
        $systemAdmin = $this->initializeSystem();
        $owner = User::factory()->create();
        $pm = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::create(['name' => 'Test Inc.', 'status' => 'active']);
        foreach ([[$owner, 'owner'], [$pm, 'pm'], [$member, 'member']] as [$user, $role]) {
            $organization->members()->create(['user_id' => $user->id, 'role' => $role, 'status' => 'active', 'joined_at' => now()]);
        }
        $project = Project::create(['organization_id' => $organization->id, 'name' => 'Alpha', 'start_date' => '2026-08-01', 'end_date' => '2026-10-01', 'created_by' => $owner->id]);
        foreach ([[$owner, 'pm'], [$pm, 'pm'], [$member, 'member']] as [$user, $role]) {
            $project->members()->create(['user_id' => $user->id, 'role' => $role, 'status' => 'active', 'joined_at' => now()]);
        }

        return compact('systemAdmin', 'owner', 'pm', 'member', 'organization', 'project');
    }

    public function test_owner_registration_creates_organization_membership(): void
    {
        $this->initializeSystem();
        $this->post('/register-owner', ['name' => 'Owner', 'email' => 'owner@example.com', 'organization_name' => 'Acme', 'password' => 'password', 'password_confirmation' => 'password'])->assertRedirect(route('home'));
        $this->assertDatabaseHas('organization_members', ['role' => 'owner', 'status' => 'active']);
    }

    public function test_initial_setup_registers_the_system_administrator(): void
    {
        $this->get('/')->assertRedirect(route('setup.admin.create'));
        $this->get(route('setup.admin.create'))->assertOk()->assertSee('システム管理者登録');

        $this->post(route('setup.admin.store'), [
            'name' => 'Initial Admin',
            'email' => 'initial-admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('admin.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'initial-admin@example.com',
            'is_system_admin' => true,
            'status' => 'active',
        ]);
        $this->get(route('setup.admin.create'))->assertRedirect(route('home'));
    }

    public function test_project_can_have_multiple_pms_without_pm_id(): void
    {
        ['owner' => $owner,'pm' => $pm] = $this->workspace();
        $this->actingAs($owner)->post(route('projects.store'), ['name' => 'Bravo', 'start_date' => '2026-09-01', 'end_date' => '2026-11-01', 'pm_user_ids' => [$owner->id, $pm->id]])->assertRedirect();
        $project = Project::where('name', 'Bravo')->firstOrFail();
        $this->assertSame(2, $project->members()->where('role', 'pm')->count());
        $this->assertFalse(\Schema::hasColumn('projects', 'pm_id'));
    }

    public function test_member_cannot_delete_project_but_owner_soft_deletes_it(): void
    {
        ['owner' => $owner,'member' => $member,'project' => $project] = $this->workspace();
        $this->actingAs($member)->delete(route('projects.destroy', $project))->assertForbidden();
        $this->actingAs($owner)->delete(route('projects.destroy', $project))->assertRedirect(route('home'));
        $this->assertSoftDeleted($project);
    }

    public function test_task_rejects_outside_assignee_and_invalid_dates(): void
    {
        ['member' => $member,'project' => $project] = $this->workspace();
        $outsider = User::factory()->create();
        $this->actingAs($member)->post(route('tasks.store', $project), ['title' => 'Invalid', 'assignee_id' => $outsider->id, 'start_date' => '2026-09-10', 'end_date' => '2026-09-01', 'progress' => 0, 'status' => 'not_started'])->assertSessionHasErrors(['assignee_id', 'end_date']);
    }

    public function test_optimistic_lock_conflict_returns_409(): void
    {
        ['member' => $member,'project' => $project] = $this->workspace();
        $task = $project->tasks()->create(['title' => 'Task', 'start_date' => '2026-09-01', 'end_date' => '2026-09-05', 'created_by' => $member->id]);
        $this->actingAs($member)->patchJson(route('tasks.update', [$project, $task]), ['progress' => 50, 'lock_version' => 0])->assertOk()->assertJsonPath('task.lock_version', 1);
        $this->actingAs($member)->patchJson(route('tasks.update', [$project, $task]), ['progress' => 60, 'lock_version' => 0])->assertStatus(409)->assertJsonPath('task.lock_version', 1);
    }

    public function test_progress_and_comment_notifications_are_queued_for_project_members(): void
    {
        Notification::fake();
        ['owner' => $owner,'member' => $member,'project' => $project] = $this->workspace();
        $task = $project->tasks()->create(['title' => 'Notify', 'start_date' => '2026-09-01', 'end_date' => '2026-09-05', 'created_by' => $owner->id]);
        $this->actingAs($owner)->patchJson(route('tasks.update', [$project, $task]), ['progress' => 75, 'lock_version' => 0])->assertOk();
        Notification::assertSentTo($member, TaskProgressChangedNotification::class);
        $this->actingAs($owner)->post(route('comments.store', [$project, $task]), ['body' => 'レビューをお願いします'])->assertRedirect();
        Notification::assertSentTo($member, TaskCommentPostedNotification::class);
    }

    public function test_completed_status_sets_progress_to_100(): void
    {
        ['member' => $member,'project' => $project] = $this->workspace();
        $task = $project->tasks()->create(['title' => 'Done', 'start_date' => '2026-09-01', 'end_date' => '2026-09-05', 'progress' => 30, 'created_by' => $member->id]);
        $this->actingAs($member)->patchJson(route('tasks.update', [$project, $task]), ['status' => 'completed', 'lock_version' => 0])
            ->assertOk()
            ->assertJsonPath('task.status', 'completed')
            ->assertJsonPath('status_label', TaskStatus::Completed->label());
        $this->assertSame(100, $task->fresh()->progress);
        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);
    }

    public function test_invitation_rejects_expired_and_accepts_valid_hashed_token(): void
    {
        ['owner' => $owner,'organization' => $organization,'project' => $project] = $this->workspace();
        $token = 'known-secure-token';
        ProjectInvitation::create(['organization_id' => $organization->id, 'project_id' => $project->id, 'invited_by' => $owner->id, 'email' => 'new@example.com', 'role' => 'member', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour()]);
        $this->get(route('invitations.show', $token))->assertOk();
        $this->post(route('invitations.accept', $token), ['name' => 'New User', 'password' => 'password', 'password_confirmation' => 'password'])->assertRedirect(route('home'));
        $this->assertDatabaseHas('project_members', ['project_id' => $project->id, 'role' => 'member']);
        $expired = 'expired-token';
        ProjectInvitation::create(['organization_id' => $organization->id, 'project_id' => $project->id, 'email' => 'old@example.com', 'role' => 'member', 'token_hash' => hash('sha256', $expired), 'expires_at' => now()->subMinute()]);
        $this->get(route('invitations.show', $expired))->assertStatus(410);
    }

    public function test_primary_gui_pages_render_with_shared_project_data(): void
    {
        ['owner' => $owner,'project' => $project] = $this->workspace();
        $task = $project->tasks()->create(['title' => 'Rendered task', 'start_date' => now()->subDay(), 'end_date' => now()->addDays(3), 'progress' => 40, 'status' => 'in_progress', 'created_by' => $owner->id]);
        $this->actingAs($owner)->get(route('home'))->assertOk()->assertSee('Alpha')->assertSee('40%');
        $this->actingAs($owner)->get(route('projects.show', $project))->assertOk()->assertSee('Rendered task');
        $this->actingAs($owner)->get(route('tasks.show', [$project, $task]))
            ->assertOk()
            ->assertSee('Rendered task')
            ->assertSee('現在のステータス')
            ->assertSee(TaskStatus::InProgress->label())
            ->assertSee('data-task-status-badge', false)
            ->assertSee('コメント');
        $this->actingAs($owner)->get(route('wbs'))->assertOk()->assertSee('WBS / ガントチャート')->assertSee('Rendered task')->assertSee('TODAY')->assertSee('scrollPrevDay')->assertSee('scrollNextDay')->assertSee('data-date="2026-10-01"', false);
    }

    public function test_only_system_admin_can_restore_soft_deleted_data(): void
    {
        ['member' => $member, 'project' => $project] = $this->workspace();
        $project->delete();
        $admin = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($member)->get(route('admin.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.index'))->assertOk()->assertSee('Alpha');
        $this->actingAs($admin)->patch(route('admin.trash.restore', ['project', $project->id]))->assertRedirect();
        $this->assertNotSoftDeleted($project);
    }

    public function test_system_admin_can_list_suspend_and_reactivate_every_organization(): void
    {
        [
            'systemAdmin' => $systemAdmin,
            'member' => $member,
            'organization' => $organization,
        ] = $this->workspace();
        $otherOrganization = Organization::create(['name' => 'Other Inc.', 'status' => 'active']);

        $this->actingAs($systemAdmin)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertSee($organization->name)
            ->assertSee($otherOrganization->name);

        $this->actingAs($member)
            ->patch(route('admin.organizations.status', $organization))
            ->assertForbidden();
        $this->assertSame('active', $organization->fresh()->status);

        $this->actingAs($systemAdmin)
            ->patch(route('admin.organizations.status', $organization))
            ->assertRedirect();
        $this->assertSame('suspended', $organization->fresh()->status);

        $suspendedLog = ActivityLog::query()->where('action', 'organization.suspended')->firstOrFail();
        $this->assertSame($systemAdmin->id, $suspendedLog->user_id);
        $this->assertSame($organization->id, $suspendedLog->organization_id);
        $this->assertSame(['status' => ['before' => 'active', 'after' => 'suspended']], $suspendedLog->changes);

        $this->actingAs($systemAdmin)
            ->patch(route('admin.organizations.status', $organization))
            ->assertRedirect();
        $this->assertSame('active', $organization->fresh()->status);

        $reactivatedLog = ActivityLog::query()->where('action', 'organization.reactivated')->firstOrFail();
        $this->assertSame($organization->id, $reactivatedLog->organization_id);
        $this->assertSame(['status' => ['before' => 'suspended', 'after' => 'active']], $reactivatedLog->changes);
    }

    public function test_invitation_can_be_revoked_and_resent_with_a_new_hash(): void
    {
        Notification::fake();
        ['owner' => $owner, 'organization' => $organization, 'project' => $project] = $this->workspace();
        $invitation = ProjectInvitation::create(['organization_id' => $organization->id, 'project_id' => $project->id, 'invited_by' => $owner->id, 'email' => 'invite@example.com', 'role' => 'member', 'token_hash' => hash('sha256', 'first'), 'expires_at' => now()->addHour()]);
        $this->actingAs($owner)->delete(route('projects.invitations.revoke', [$project, $invitation]))->assertRedirect();
        $this->assertNotNull($invitation->fresh()->revoked_at);
        $oldHash = $invitation->token_hash;
        $this->actingAs($owner)->patch(route('projects.invitations.resend', [$project, $invitation]))->assertRedirect();
        $this->assertNull($invitation->fresh()->revoked_at);
        $this->assertNotSame($oldHash, $invitation->fresh()->token_hash);
    }
}
