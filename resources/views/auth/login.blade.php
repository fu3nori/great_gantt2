@extends('layouts.app')
@section('title', 'ログイン')
@section('content')
<div class="auth-shell">
    <section class="auth-intro">
        <div class="brand-mark brand-mark-lg"><i class="bi bi-bezier2"></i></div>
        <p class="eyebrow mt-4">PROJECT OPERATIONS</p>
        <h1>チームの計画を、<br>ひとつの時間軸へ。</h1>
        <p class="lead">プロジェクト、チケット、WBSをリアルタイムにつなぐワークスペース。</p>
        <div class="auth-feature"><i class="bi bi-broadcast-pin"></i><span><strong>リアルタイム同期</strong><small>変更をチーム全員へ即時反映</small></span></div>
        <div class="auth-feature"><i class="bi bi-diagram-3"></i><span><strong>階層WBS</strong><small>計画と実績を一画面で把握</small></span></div>
    </section>
    <section class="auth-card">
        <p class="eyebrow">WELCOME BACK</p><h2>ログイン</h2><p class="text-secondary mb-4">ワークスペースへ続行します</p>
        <form method="post" action="{{ url('/login') }}">@csrf
            <label class="form-label">メールアドレス</label><div class="input-group mb-3"><span class="input-group-text"><i class="bi bi-envelope"></i></span><input class="form-control form-control-lg" type="email" name="email" value="{{ old('email') }}" placeholder="name@company.jp" required autofocus></div>
            <label class="form-label">パスワード</label><div class="input-group mb-3"><span class="input-group-text"><i class="bi bi-lock"></i></span><input class="form-control form-control-lg" type="password" name="password" required></div>
            <div class="form-check mb-4"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label" for="remember">ログイン状態を保持</label></div>
            <button class="btn btn-primary btn-lg w-100">ログイン <i class="bi bi-arrow-right ms-2"></i></button>
        </form>
        <div class="text-center mt-4 text-secondary">初めて利用しますか？ <a href="{{ route('owner.register') }}">事業者を登録</a></div>
        <div class="demo-credentials mt-4"><i class="bi bi-lightning-charge"></i><span>デモ: <code>demo@example.com</code> / <code>password</code></span></div>
    </section>
</div>
@endsection
