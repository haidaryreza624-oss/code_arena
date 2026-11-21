@extends('layouts.admin')

@section('title', 'Teams')

@section('content')
<h1 class="h2">Teams</h1>
<p class="text-muted">Team and participation management will be available here.</p>
<a class="btn btn-outline-primary" href="{{ route('admin.dashboard') }}">Back to dashboard</a>
@endsection
