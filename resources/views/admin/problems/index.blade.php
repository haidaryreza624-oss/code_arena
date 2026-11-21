@extends('layouts.admin')

@section('title', 'Problem bank')

@section('content')
<h1 class="h2">Problem bank</h1>
<p class="text-muted">Problem and test case management will be available here.</p>
<a class="btn btn-outline-primary" href="{{ route('admin.dashboard') }}">Back to dashboard</a>
@endsection
