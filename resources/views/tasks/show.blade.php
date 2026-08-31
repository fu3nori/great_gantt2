@extends('layouts.app')
@section('title', $task->title)
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a class="back-link" href="{{ route('projects.show', $project) }}"><i class="bi bi-arrow-left"></i> {{ $project->name }}</a>
        <p class="eyebrow mt-3">TICKET #{{ $task->id }}</p>
        <h1>{{ $task->title }}</h1>
    </div>
    <div class="current-task-status" aria-live="polite" aria-atomic="true">
        <span><i class="bi bi-activity"></i> 現在のステータス</span>
        <strong
            class="task-status status-{{ $task->status->value }}"
            data-task-status-badge
            data-saved-status="{{ $task->status->value }}"
            data-saved-label="{{ $task->status->label() }}"
        >{{ $task->status->label() }}</strong>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="content-panel p-4">
            <div class="panel-heading px-0 pt-0">
                <div><p class="eyebrow">DETAIL</p><h3>タスク情報</h3></div>
                <span class="save-indicator" id="saveIndicator"><i class="bi bi-cloud-check"></i> 保存済み</span>
            </div>

            <form method="post" action="{{ route('tasks.update', [$project, $task]) }}" data-ajax-task>
                @csrf
                @method('PATCH')
                <input type="hidden" name="lock_version" value="{{ $task->lock_version }}">

                <label class="form-label">タスク名</label>
                <input class="form-control form-control-lg mb-3" name="title" value="{{ $task->title }}" required>

                <label class="form-label">説明</label>
                <textarea class="form-control mb-3" name="description" rows="5">{{ $task->description }}</textarea>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">担当者</label>
                        <select class="form-select" name="assignee_id">
                            <option value="">未割当</option>
                            @foreach($project->members as $member)
                                <option value="{{ $member->user_id }}" @selected($task->assignee_id === $member->user_id)>{{ $member->user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ステータス</label>
                        <select class="form-select" name="status">
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}" @selected($task->status === $status)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">開始日</label>
                        <input class="form-control" type="date" name="start_date" value="{{ $task->start_date->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">終了日</label>
                        <input class="form-control" type="date" name="end_date" value="{{ $task->end_date->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">進捗率</label>
                        <div class="input-group">
                            <input class="form-control" type="number" name="progress" min="0" max="100" value="{{ $task->progress }}">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end">
                    <button class="btn btn-primary"><i class="bi bi-cloud-arrow-up"></i> 変更を保存</button>
                </div>
            </form>
        </div>

        <div class="content-panel p-4 mt-4">
            <div class="panel-heading px-0 pt-0">
                <div><p class="eyebrow">DISCUSSION</p><h3>コメント <span>{{ $task->comments->count() }}</span></h3></div>
            </div>
            <div class="comment-list">@include('tasks._comments', ['comments' => $task->comments, 'depth' => 0])</div>
            <form method="post" action="{{ route('comments.store', [$project, $task]) }}" class="comment-form mt-4">
                @csrf
                <input type="hidden" name="parent_id" id="parentId">
                <div class="reply-preview d-none" id="replyPreview"><span></span><button type="button" class="btn-close"></button></div>
                <textarea class="form-control" name="body" rows="3" placeholder="コメントを入力..." required></textarea>
                <div class="text-end mt-2"><button class="btn btn-primary"><i class="bi bi-send"></i> 投稿</button></div>
            </form>
        </div>
    </div>

    <div class="col-xl-4">
        <aside class="side-panel">
            <p class="eyebrow">PROGRESS</p>
            <div class="progress-donut" style="--progress:{{ $task->progress }}" data-task-progress-donut><strong data-task-progress-text>{{ $task->progress }}%</strong></div>
            <div class="progress app-progress"><div class="progress-bar" style="width:{{ $task->progress }}%" data-task-progress-bar></div></div>
            <hr>
            <dl>
                <dt>プロジェクト</dt><dd><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></dd>
                <dt>担当者</dt><dd>{{ $task->assignee?->name ?? '未割当' }}</dd>
                <dt>期間</dt><dd>{{ $task->start_date->format('Y/m/d') }} — {{ $task->end_date->format('Y/m/d') }}</dd>
                <dt>更新</dt><dd>{{ $task->updated_at->diffForHumans() }}</dd>
            </dl>
            <a class="btn btn-outline-primary w-100" href="{{ route('wbs') }}"><i class="bi bi-bar-chart-steps"></i> WBSで表示</a>
        </aside>

        @can('delete', $task)
            <form method="post" action="{{ route('tasks.destroy', [$project, $task]) }}" class="mt-3" onsubmit="return confirm('タスクを削除しますか？')">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> タスクを削除</button>
            </form>
        @endcan
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.reply-button').forEach(button => button.addEventListener('click', () => {
    const parentId = document.getElementById('parentId');
    const replyPreview = document.getElementById('replyPreview');
    parentId.value = button.dataset.commentId;
    replyPreview.classList.remove('d-none');
    replyPreview.querySelector('span').textContent = `${button.dataset.commentName} さんへ返信`;
    document.querySelector('.comment-form textarea').focus();
}));
document.querySelector('#replyPreview button')?.addEventListener('click', () => {
    document.getElementById('parentId').value = '';
    document.getElementById('replyPreview').classList.add('d-none');
});
</script>
@endpush
@endsection
