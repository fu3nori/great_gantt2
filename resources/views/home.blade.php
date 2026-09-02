@extends('layouts.app')
@section('title', 'HOME')
@section('content')
<div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><p class="eyebrow">WORKSPACE OVERVIEW</p><h1>プロジェクト</h1><p class="text-secondary mb-0">進行中の仕事を俯瞰し、次のアクションへ。</p></div>
    @can('create', App\Models\Project::class)<a class="btn btn-primary" href="{{ route('projects.create') }}"><i class="bi bi-plus-lg me-1"></i> プロジェクト作成</a>@endcan
</div>
<div class="summary-strip mb-4"><div><span>プロジェクト</span><strong>{{ $projects->count() }}</strong></div><div><span>タスク</span><strong>{{ $projects->sum(fn($p) => $p->tasks->count()) }}</strong></div><div><span>完了</span><strong>{{ $projects->sum(fn($p) => $p->tasks->where('status.value', 'completed')->count()) }}</strong></div><a href="{{ route('wbs') }}"><i class="bi bi-bar-chart-steps"></i> WBSを開く</a></div>
@if($projects->isEmpty())
<div class="empty-state"><div class="empty-icon"><i class="bi bi-folder-plus"></i></div><h3>最初のプロジェクトを作成</h3><p>期間とPMを決めて、チームの計画を始めましょう。</p>@can('create', App\Models\Project::class)<a class="btn btn-primary" href="{{ route('projects.create') }}">プロジェクト作成</a>@endcan</div>
@else
<div class="row g-4" id="projectGrid">@foreach($projects as $project)
    <div class="col-xl-4 col-md-6" data-project-id="{{ $project->id }}"><article class="project-card h-100">
        <div class="d-flex justify-content-between align-items-start"><span class="project-icon"><i class="bi bi-folder2-open"></i></span><div class="d-flex align-items-center gap-2">@can('inviteMember', $project)<button type="button" class="btn btn-sm btn-light project-card-action" data-bs-toggle="modal" data-bs-target="#homeInviteModal{{ $project->id }}" aria-label="{{ $project->name }}へユーザーを招待"><i class="bi bi-person-plus"></i> 招待</button>@endcan<span class="status-dot"><i></i> {{ $project->status === 'active' ? '進行中' : $project->status }}</span></div></div>
        <a class="stretched-link project-title" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a><p class="project-description">{{ Str::limit($project->description ?: '説明はまだありません。', 78) }}</p>
        <div class="avatar-stack mb-3">@foreach($project->members->where('role','pm')->take(4) as $membership)<span class="avatar" title="{{ $membership->user->name }}">{{ mb_substr($membership->user->name,0,1) }}</span>@endforeach <small>PM {{ $project->members->where('role','pm')->pluck('user.name')->join(' / ') }}</small></div>
        <div class="progress-label"><span>進捗</span><strong data-project-progress>{{ $project->progress() }}%</strong></div><div class="progress app-progress"><div class="progress-bar" style="width:{{ $project->progress() }}%"></div></div>
        <div class="project-meta"><span><i class="bi bi-calendar3"></i>{{ $project->start_date->format('Y/m/d') }} — {{ $project->end_date->format('Y/m/d') }}</span><span><i class="bi bi-check2-square"></i>{{ $project->tasks->count() }} tasks</span></div>
    </article></div>
@endforeach</div>

@foreach($projects as $project)
    @can('inviteMember', $project)
    <div class="modal fade" id="homeInviteModal{{ $project->id }}" tabindex="-1" aria-labelledby="homeInviteModalLabel{{ $project->id }}">
        <div class="modal-dialog"><form class="modal-content" method="post" action="{{ route('projects.invitations.store', $project) }}">@csrf
            <div class="modal-header"><div><p class="eyebrow">INVITE MEMBER</p><h4 class="modal-title" id="homeInviteModalLabel{{ $project->id }}">{{ $project->name }}へ招待</h4></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button></div>
            <div class="modal-body">@include('invitations._form', ['project' => $project])</div>
            <div class="modal-footer"><button class="btn btn-primary"><i class="bi bi-send"></i> 招待メールを送信</button></div>
        </form></div>
    </div>
    @endcan
@endforeach
@endif
@endsection
