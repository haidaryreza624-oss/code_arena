@extends('layouts.admin')

@section('title', 'Add problem')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0">Add problem</h1>
    <a class="btn btn-outline-secondary" href="{{ route('admin.problems.index') }}">Back</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.problems.store') }}">
            @csrf
            @include('admin.problems.partials.form')
            <button class="btn btn-primary" type="submit">Create problem</button>
        </form>
    </div>
</div>
@endsection
