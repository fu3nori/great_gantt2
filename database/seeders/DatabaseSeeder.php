<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $admin = User::factory()->create(['name' => 'システム管理者', 'email' => 'admin@example.com', 'password' => 'password', 'is_system_admin' => true]);
        $owner = User::factory()->create(['name' => '山田 太郎', 'email' => 'demo@example.com', 'password' => 'password']);
        $pm = User::factory()->create(['name' => '佐藤 花子', 'email' => 'pm@example.com', 'password' => 'password']);
        $member = User::factory()->create(['name' => '鈴木 一郎', 'email' => 'member@example.com', 'password' => 'password']);
        $organization = Organization::create(['name' => '株式会社デモワークス', 'status' => 'active']);
        foreach ([[$owner, 'owner'], [$pm, 'pm'], [$member, 'member']] as [$user,$role]) {
            $organization->members()->create(['user_id' => $user->id, 'role' => $role, 'status' => 'active', 'joined_at' => now()]);
        }

        $project = Project::create(['organization_id' => $organization->id, 'name' => 'ECサイトリニューアル', 'description' => '顧客体験と運用効率を高める次世代ECプラットフォームへの刷新プロジェクト。', 'start_date' => now()->subDays(12), 'end_date' => now()->addDays(35), 'status' => 'active', 'created_by' => $owner->id]);
        foreach ([[$owner, 'pm'], [$pm, 'pm'], [$member, 'member']] as [$user,$role]) {
            $project->members()->create(['user_id' => $user->id, 'role' => $role, 'status' => 'active', 'joined_at' => now()]);
        }
        $planning = Task::create(['project_id' => $project->id, 'title' => '要件定義', 'description' => 'ステークホルダー要件の整理', 'assignee_id' => $owner->id, 'start_date' => now()->subDays(12), 'end_date' => now()->subDays(3), 'progress' => 100, 'status' => 'completed', 'sort_order' => 1, 'created_by' => $owner->id]);
        Task::create(['project_id' => $project->id, 'parent_id' => $planning->id, 'title' => 'ユーザー要求ヒアリング', 'assignee_id' => $owner->id, 'start_date' => now()->subDays(12), 'end_date' => now()->subDays(7), 'progress' => 100, 'status' => 'completed', 'sort_order' => 1, 'created_by' => $owner->id]);
        $design = Task::create(['project_id' => $project->id, 'title' => 'UI / UX設計', 'assignee_id' => $pm->id, 'start_date' => now()->subDays(5), 'end_date' => now()->addDays(8), 'progress' => 62, 'status' => 'in_progress', 'sort_order' => 2, 'created_by' => $owner->id]);
        Task::create(['project_id' => $project->id, 'parent_id' => $design->id, 'title' => 'デザインシステム', 'assignee_id' => $pm->id, 'start_date' => now()->subDays(3), 'end_date' => now()->addDays(4), 'progress' => 70, 'status' => 'review', 'sort_order' => 1, 'created_by' => $pm->id]);
        Task::create(['project_id' => $project->id, 'title' => 'API実装', 'assignee_id' => $member->id, 'start_date' => now()->addDays(2), 'end_date' => now()->addDays(20), 'progress' => 25, 'status' => 'in_progress', 'sort_order' => 3, 'created_by' => $pm->id]);
        Task::create(['project_id' => $project->id, 'title' => '総合テスト', 'assignee_id' => $owner->id, 'start_date' => now()->addDays(21), 'end_date' => now()->addDays(32), 'progress' => 0, 'status' => 'not_started', 'sort_order' => 4, 'created_by' => $owner->id]);

        $project2 = Project::create(['organization_id' => $organization->id, 'name' => '社内ポータル刷新', 'description' => '情報共有を一元化する社内ポータル構築。', 'start_date' => now()->subMonth(), 'end_date' => now()->addMonths(2), 'status' => 'active', 'created_by' => $owner->id]);
        foreach ([[$owner, 'pm'], [$member, 'member']] as [$user,$role]) {
            $project2->members()->create(['user_id' => $user->id, 'role' => $role, 'status' => 'active', 'joined_at' => now()]);
        }
        Task::create(['project_id' => $project2->id, 'title' => '現状分析', 'assignee_id' => $owner->id, 'start_date' => now()->subMonth(), 'end_date' => now()->subDays(14), 'progress' => 85, 'status' => 'review', 'sort_order' => 1, 'created_by' => $owner->id]);
        Task::create(['project_id' => $project2->id, 'title' => '検索基盤実装', 'assignee_id' => $member->id, 'start_date' => now()->subDays(10), 'end_date' => now()->addDays(12), 'progress' => 45, 'status' => 'in_progress', 'sort_order' => 2, 'created_by' => $owner->id]);
    }
}
