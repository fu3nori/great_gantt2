<label class="form-label" for="invite-name-{{ $project->id }}">氏名</label>
<input class="form-control" id="invite-name-{{ $project->id }}" name="name" value="{{ old('name') }}" maxlength="255" autocomplete="name" required>

<label class="form-label mt-3" for="invite-email-{{ $project->id }}">メールアドレス</label>
<input class="form-control" id="invite-email-{{ $project->id }}" type="email" name="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required>

<label class="form-label mt-3" for="invite-role-{{ $project->id }}">ポジション</label>
<select class="form-select" id="invite-role-{{ $project->id }}" name="role" required>
    <option value="member" @selected(old('role') === 'member')>メンバー</option>
    @can('invitePm', $project)<option value="pm" @selected(old('role') === 'pm')>PM</option>@endcan
</select>

<p class="date-hint mt-3 mb-0"><i class="bi bi-shield-lock"></i> 招待リンクの有効期限は7日間です。リンクを開いた方だけが参加できます。</p>
