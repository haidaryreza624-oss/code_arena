@extends('layouts.team')

@section('title', 'Team login')

@section('content')
<div class="row justify-content-center align-items-center" style="min-height: 75vh">
    <div class="col-sm-10 col-md-7 col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-2">Contest team login</h1>
                <p class="text-muted mb-4">Sign in with the credentials issued for the active contest.</p>
                <form method="POST" action="{{ route('team.login.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control @error('username') is-invalid @enderror" id="username"
                               name="username" value="{{ old('username') }}" autocomplete="username" required autofocus>
                        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control @error('password') is-invalid @enderror" id="password"
                               name="password" type="password" autocomplete="current-password" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Sign in</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
