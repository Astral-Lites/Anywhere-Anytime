@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <div class="auth-box card">
        <h1>Log in</h1>
        <p class="lead">Use your email and password to continue.</p>

        @include('partials.errors')

        <form method="POST" action="{{ route('login') }}" class="stacked">
            @csrf

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">

            <label class="check">
                <input type="checkbox" name="remember" value="1">
                Keep me logged in
            </label>

            <button type="submit" class="btn">Log in</button>
        </form>

        <p>No account yet? <a href="{{ route('register') }}">Create an account</a></p>
    </div>
@endsection
