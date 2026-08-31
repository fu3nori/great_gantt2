<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Great Gantt') · Great Gantt</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-app">
@auth
<nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}"><span class="brand-mark"><i class="bi bi-bezier2"></i></span><strong>Great Gantt</strong></a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#appNav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="appNav">
            <ul class="navbar-nav me-auto ms-lg-4 gap-lg-1">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}"><i class="bi bi-grid-1x2 me-1"></i> HOME</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('wbs') ? 'active' : '' }}" href="{{ route('wbs') }}"><i class="bi bi-bar-chart-steps me-1"></i> WBS</a></li>
                @can('create', App\Models\Project::class)<li class="nav-item"><a class="nav-link" href="{{ route('projects.create') }}"><i class="bi bi-plus-circle me-1"></i> プロジェクト作成</a></li>@endcan
                @if(auth()->user()->is_system_admin)<li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}" href="{{ route('admin.index') }}"><i class="bi bi-shield-check me-1"></i> 管理</a></li>@endif
            </ul>
            <div class="dropdown">
                <button class="btn btn-sm user-menu dropdown-toggle" data-bs-toggle="dropdown"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>{{ auth()->user()->name }}</button>
                <div class="dropdown-menu dropdown-menu-end"><form method="post" action="{{ route('logout') }}">@csrf<button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>ログアウト</button></form></div>
            </div>
        </div>
    </div>
</nav>
@endauth

<main class="@yield('main-class', 'container py-4 py-lg-5')">
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show app-alert"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    @if($errors->any())<div class="alert alert-danger app-alert"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>入力内容を確認してください。</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<div class="toast-container position-fixed bottom-0 end-0 p-3"><div id="appToast" class="toast border-0" role="alert"><div class="toast-header"><i class="bi bi-info-circle me-2"></i><strong class="me-auto">Great Gantt</strong><button class="btn-close" data-bs-dismiss="toast"></button></div><div class="toast-body"></div></div></div>
@stack('scripts')
</body>
</html>
