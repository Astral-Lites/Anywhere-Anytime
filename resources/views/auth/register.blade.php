@extends('layouts.app')

@section('title', 'Create account')

@section('content')
    <div class="auth-box card">
        <h1>Create account</h1>
        <p class="lead">New accounts are created as residents. Admin accounts are set up by the system.</p>

        @include('partials.errors')

        <form method="POST" action="{{ route('register') }}" class="stacked">
            @csrf

            <label for="name">Full name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email">

            <label for="password">Password (at least 8 characters)</label>
            <input type="password" id="password" name="password" required autocomplete="new-password">

            <label for="password_confirmation">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">

            <button type="submit" class="btn">Create account</button>
        </form>

        <p>Already registered? <a href="{{ route('login') }}">Log in</a></p>
    </div>
@endsection
