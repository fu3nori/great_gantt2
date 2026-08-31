@extends('layouts.app')
@section('title','プロジェクト作成')
@section('content')
<div class="page-heading mb-4"><a class="back-link" href="{{ route('home') }}"><i class="bi bi-arrow-left"></i> HOME</a><p class="eyebrow mt-3">NEW PROJECT</p><h1>新しいプロジェクト</h1><p class="text-secondary">{{ $organization->name }} の計画を作成します。</p></div>
<form method="post" action="{{ route('projects.store') }}">@include('projects._form')</form>
@endsection
