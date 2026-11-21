@extends('layouts.admin')

@section('title', 'Submissions')

@section('content')
<h1 class="h2">Submissions</h1>
<p class="text-muted">Submission and judging management will be available here.</p>
<a class="btn btn-outline-primary" href="{{ route('admin.dashboard') }}">Back to dashboard</a>
@endsection
