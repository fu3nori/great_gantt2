@extends('layouts.app')
@section('title', 'パスワード設定')
@section('content')
<div class="auth-shell auth-shell-compact">
    <section class="auth-intro">
        <div class="brand-mark brand-mark-lg"><i class="bi bi-shield-lock"></i></div>
        <p class="eyebrow mt-4">CREATE YOUR ACCOUNT</p>
        <h1>パスワードを設定</h1>
        <p class="lead">パスワードを設定すると、{{ $invitation->project?->name }} に参加できます。</p>
    </section>

    <section class="auth-card">
        <p class="eyebrow">ACCOUNT SETUP</p>
        <h2>アカウント情報</h2>

        <div class="invitation-summary">
            @if($invitation->name)
                <span>氏名</span><strong>{{ $invitation->name }}</strong>
            @endif
            <span>メールアドレス</span><strong>{{ $invitation->email }}</strong>
            <span>プロジェクト</span><strong>{{ $invitation->project?->name }}</strong>
            <span>ポジション</span><strong>{{ $invitation->role === 'pm' ? 'PM' : 'メンバー' }}</strong>
        </div>

        <form method="post" action="{{ route('invitations.accept', $token) }}">
            @csrf
            @if(!$invitation->name)
                <label class="form-label" for="name">お名前</label>
                <input class="form-control mb-3" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required>
            @endif

            <label class="form-label" for="password">パスワード</label>
            <input class="form-control mb-2" id="password" type="password" name="password" minlength="8" autocomplete="new-password" required>
            <p class="form-text mb-3">8文字以上で入力してください。</p>

            <label class="form-label" for="password_confirmation">パスワード確認</label>
            <input class="form-control mb-4" id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>

            <button class="btn btn-primary btn-lg w-100">アカウントを作成して参加</button>
        </form>

        <a class="btn btn-link w-100 mt-2" href="{{ route('invitations.show', $token) }}">招待内容に戻る</a>
    </section>
</div>
@endsection
