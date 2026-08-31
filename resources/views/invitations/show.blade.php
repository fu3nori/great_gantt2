@extends('layouts.app')
@section('title','プロジェクト招待')
@section('content')
<div class="auth-shell auth-shell-compact"><section class="auth-intro"><div class="brand-mark brand-mark-lg"><i class="bi bi-envelope-paper-heart"></i></div><p class="eyebrow mt-4">YOU ARE INVITED</p><h1>{{ $invitation->project?->name }}</h1><p class="lead">{{ $invitation->inviter?->name }} さんから {{ $invitation->role === 'pm' ? 'PM' : 'メンバー' }} として招待されています。</p></section><section class="auth-card"><p class="eyebrow">JOIN PROJECT</p><h2>招待を承認</h2><div class="invitation-summary"><span>事業者</span><strong>{{ $invitation->organization->name }}</strong><span>招待先</span><strong>{{ $invitation->email }}</strong><span>有効期限</span><strong>{{ $invitation->expires_at->format('Y/m/d H:i') }}</strong></div>
@if(App\Models\User::where('email',$invitation->email)->exists())
@auth @if(auth()->user()->email === $invitation->email)<form method="post" action="{{ route('invitations.accept',$token) }}">@csrf<button class="btn btn-primary btn-lg w-100">招待を承認</button></form>@else<div class="alert alert-warning">{{ $invitation->email }} のアカウントでログインしてください。</div>@endif @else<a class="btn btn-primary btn-lg w-100" href="{{ route('login') }}">ログインして承認</a>@endauth
@else<form method="post" action="{{ route('invitations.accept',$token) }}">@csrf<label class="form-label">お名前</label><input class="form-control mb-3" name="name" required><label class="form-label">パスワード</label><input class="form-control mb-3" type="password" name="password" required><label class="form-label">パスワード確認</label><input class="form-control mb-4" type="password" name="password_confirmation" required><button class="btn btn-primary btn-lg w-100">アカウントを作成して参加</button></form>@endif
</section></div>
@endsection
