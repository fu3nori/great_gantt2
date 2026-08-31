@extends('layouts.app')
@section('title', '事業者登録')
@section('content')
<div class="auth-shell auth-shell-compact">
    <section class="auth-intro"><div class="brand-mark brand-mark-lg"><i class="bi bi-bezier2"></i></div><p class="eyebrow mt-4">START YOUR WORKSPACE</p><h1>計画が見えると、<br>チームは速くなる。</h1><p class="lead">事業者と代表者アカウントを同時に作成します。</p></section>
    <section class="auth-card"><p class="eyebrow">CREATE WORKSPACE</p><h2>事業者登録</h2><form method="post" action="{{ url('/register-owner') }}" class="mt-4">@csrf
        <label class="form-label">事業者名</label><input class="form-control form-control-lg mb-3" name="organization_name" value="{{ old('organization_name') }}" required>
        <label class="form-label">代表者名</label><input class="form-control form-control-lg mb-3" name="name" value="{{ old('name') }}" required>
        <label class="form-label">メールアドレス</label><input class="form-control form-control-lg mb-3" type="email" name="email" value="{{ old('email') }}" required>
        <div class="row"><div class="col-md-6"><label class="form-label">パスワード</label><input class="form-control" type="password" name="password" required></div><div class="col-md-6"><label class="form-label">パスワード確認</label><input class="form-control" type="password" name="password_confirmation" required></div></div>
        <button class="btn btn-primary btn-lg w-100 mt-4">ワークスペースを作成</button></form><div class="text-center mt-4"><a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> ログインへ戻る</a></div></section>
</div>
@endsection
