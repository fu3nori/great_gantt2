@csrf
@if($project->exists) @method('PUT') @endif
<div class="row g-4">
    <div class="col-lg-8"><div class="form-section"><h5><span>01</span> 基本情報</h5>
        <label class="form-label">プロジェクト名 <b>*</b></label><input class="form-control form-control-lg" name="name" value="{{ old('name', $project->name) }}" placeholder="例: ECサイトリニューアル" required>
        <label class="form-label mt-3">説明</label><textarea class="form-control" name="description" rows="5" placeholder="目的、背景、完了条件など">{{ old('description', $project->description) }}</textarea>
    </div></div>
    <div class="col-lg-4"><div class="form-section"><h5><span>02</span> スケジュール</h5>
        <label class="form-label">開始日</label><input class="form-control" type="date" name="start_date" value="{{ old('start_date', $project->start_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
        <label class="form-label mt-3">終了予定日</label><input class="form-control" type="date" name="end_date" value="{{ old('end_date', $project->end_date?->format('Y-m-d') ?? now()->addMonth()->format('Y-m-d')) }}" required>
        <div class="date-hint mt-3"><i class="bi bi-info-circle"></i> WBSの日付範囲はタスク期間から自動調整されます。</div>
    </div></div>
    <div class="col-12"><div class="form-section"><div class="d-flex justify-content-between"><h5><span>03</span> プロジェクトPM</h5><span class="badge text-bg-light">複数選択可</span></div>
        <input class="form-control mb-3" id="pmSearch" placeholder="名前またはメールで検索...">
        <div class="member-picker" id="pmList">@php($selected = collect(old('pm_user_ids', $project->exists ? $project->members->where('role','pm')->pluck('user_id')->all() : [auth()->id()])))
        @foreach($pmCandidates as $candidate)<label class="member-option" data-search="{{ strtolower($candidate->name.' '.$candidate->email) }}"><input class="form-check-input" type="checkbox" name="pm_user_ids[]" value="{{ $candidate->id }}" @checked($selected->contains($candidate->id))><span class="avatar">{{ mb_substr($candidate->name,0,1) }}</span><span><strong>{{ $candidate->name }}</strong><small>{{ $candidate->email }}{{ $candidate->id === auth()->id() ? ' · あなた' : '' }}</small></span><i class="bi bi-check-circle-fill"></i></label>@endforeach
        </div>
    </div></div>
</div>
<div class="form-actions"><a class="btn btn-light" href="{{ $project->exists ? route('projects.show',$project) : route('home') }}">キャンセル</a><button class="btn btn-primary px-4"><i class="bi bi-check2 me-1"></i>{{ $project->exists ? '変更を保存' : 'プロジェクト作成' }}</button></div>
@push('scripts')<script>document.getElementById('pmSearch')?.addEventListener('input',e=>document.querySelectorAll('#pmList .member-option').forEach(x=>x.hidden=!x.dataset.search.includes(e.target.value.toLowerCase())));</script>@endpush
