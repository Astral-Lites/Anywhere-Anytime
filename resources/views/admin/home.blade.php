@extends('layouts.app')

@section('title', 'Admin dashboard')

@section('content')
    <h1>Admin dashboard</h1>
    <p class="lead">Review facility submissions from residents.</p>

    <div class="stats">
        <div class="card stat"><strong>{{ $counts['pending'] }}</strong><span>Waiting for review</span></div>
        <div class="card stat"><strong>{{ $counts['approved'] }}</strong><span>Approved</span></div>
        <div class="card stat"><strong>{{ $counts['rejected'] }}</strong><span>Rejected</span></div>
        <div class="card stat"><strong>{{ $counts['updates'] }}</strong><span>Update requests waiting</span></div>
        <div class="card stat"><strong>{{ $counts['residents'] }}</strong><span>Registered residents</span></div>
    </div>

    <div class="inline-actions">
        <a class="btn" href="{{ route('admin.submissions.index') }}">Review submissions</a>
        <a class="btn btn-light" href="{{ route('admin.update-requests.index') }}">Review update requests</a>
        <a class="btn btn-light" href="{{ route('admin.reports.index') }}">Accessibility gap report</a>
    </div>
@endsection
