@extends('layouts.admin')

@section('title', 'Contests')

@section('content')
<h1 class="h2">Contests</h1>
<p class="text-muted">Contest management will be available here.</p>
<a class="btn btn-outline-primary" href="{{ route('admin.dashboard') }}">Back to dashboard</a>
@endsection
