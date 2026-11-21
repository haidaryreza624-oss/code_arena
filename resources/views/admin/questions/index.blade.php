@extends('layouts.admin')

@section('title', 'Questions')

@section('content')
<h1 class="h2">Questions and clarifications</h1>
<p class="text-muted">Contestant questions and clarification management will be available here.</p>
<a class="btn btn-outline-primary" href="{{ route('admin.dashboard') }}">Back to dashboard</a>
@endsection
