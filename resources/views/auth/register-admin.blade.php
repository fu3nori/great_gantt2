@extends('layouts.app')
@section('title', '初期セットアップ')
@section('content')
<div class="auth-shell auth-shell-compact">
    <section class="auth-intro">
        <div class="brand-mark brand-mark-lg"><i class="bi bi-shield-lock"></i></div>
        <p class="eyebrow mt-4">INITIAL SETUP</p>
        <h1>Great Ganttを<br>はじめる準備</h1>
        <p class="lead">最初に、システム全体を管理する管理者アカウントを1件登録します。</p>
        <div class="auth-feature"><i class="bi bi-buildings"></i><span><strong>システム全体を管理</strong><small>事業者・ユーザー・削除済みデータを横断管理できます</small></span></div>
        <div class="auth-feature"><i class="bi bi-person-check"></i><span><strong>事業者とは別の権限</strong><small>登録後、各事業者の代表者を追加できます</small></span></div>
    </section>
    <section class="auth-card">
        <p class="eyebrow">SYSTEM ADMINISTRATOR</p>
        <h2>システム管理者登録</h2>
        <p class="text-secondary mb-4">この画面は初回セットアップ時のみ表示されます。</p>
        <form method="post" action="{{ route('setup.admin.store') }}">
            @csrf
            <label class="form-label" for="adminName">管理者名</label>
            <input class="form-control form-control-lg mb-3" id="adminName" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">

            <label class="form-label" for="adminEmail">メールアドレス</label>
            <input class="form-control form-control-lg mb-3" id="adminEmail" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="adminPassword">パスワード</label>
                    <input class="form-control" id="adminPassword" type="password" name="password" minlength="8" required autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="adminPasswordConfirmation">パスワード確認</label>
                    <input class="form-control" id="adminPasswordConfirmation" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password">
                </div>
            </div>

            <button class="btn btn-primary btn-lg w-100 mt-4"><i class="bi bi-shield-check me-2"></i>管理者を登録して開始</button>
        </form>
    </section>
</div>
@endsection
