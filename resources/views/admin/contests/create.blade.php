@extends('layouts.admin')

@section('title', 'Create contest')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Create contest</h1>
        <p class="text-muted mb-0">A new contest starts as a draft.</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.contests.index') }}">Back</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.contests.store') }}">
            @csrf
            @include('admin.contests.partials.form')
            <button class="btn btn-primary" type="submit">Create draft</button>
        </form>
    </div>
</div>
@endsection
