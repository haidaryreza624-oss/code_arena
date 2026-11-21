@extends('layouts.admin')

@section('title', 'Edit problem')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0">Edit {{ $problem->code }}</h1>
    <a class="btn btn-outline-secondary" href="{{ route('admin.problems.show', $problem) }}">Back</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.problems.update', $problem) }}">
            @csrf
            @method('PUT')
            @include('admin.problems.partials.form')
            <button class="btn btn-primary" type="submit">Save changes</button>
        </form>
    </div>
</div>
@endsection
