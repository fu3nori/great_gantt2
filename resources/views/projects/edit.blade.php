@extends('layouts.app')
@section('title','プロジェクト編集')
@section('content')
<div class="page-heading mb-4"><a class="back-link" href="{{ route('projects.show',$project) }}"><i class="bi bi-arrow-left"></i> プロジェクト</a><p class="eyebrow mt-3">PROJECT SETTINGS</p><h1>プロジェクト編集</h1></div>
<form method="post" action="{{ route('projects.update',$project) }}">@include('projects._form')</form>
@endsection
