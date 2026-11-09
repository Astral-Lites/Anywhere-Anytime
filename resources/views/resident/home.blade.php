@extends('layouts.app')

@section('title', 'Resident dashboard')

@section('content')
    <h1>Welcome, {{ auth()->user()->name }}</h1>
    <p class="lead">Submit community facilities and their accessibility features. An admin reviews each submission before it appears in the directory.</p>

    <div class="stats">
        <div class="card stat"><strong>{{ $counts['total'] }}</strong><span>Total submissions</span></div>
        <div class="card stat"><strong>{{ $counts['pending'] }}</strong><span>Waiting for review</span></div>
        <div class="card stat"><strong>{{ $counts['approved'] }}</strong><span>Approved</span></div>
        <div class="card stat"><strong>{{ $counts['rejected'] }}</strong><span>Rejected</span></div>
        <div class="card stat"><strong>{{ $counts['updates'] }}</strong><span>Update requests waiting</span></div>
    </div>

    <div class="inline-actions">
        <a class="btn" href="{{ route('resident.submissions.create') }}">Submit a facility</a>
        <a class="btn btn-light" href="{{ route('directory') }}">Explore the map</a>
    </div>

    <h2>Latest submissions</h2>
    @if ($recent->isEmpty())
        <div class="empty">You have not submitted any facilities yet. Use "Submit a facility" to add the first one.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Facility</th><th>Category</th><th>Status</th><th>Submitted</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($recent as $facility)
                        <tr>
                            <td>{{ $facility->name }}</td>
                            <td>{{ $facility->category }}</td>
                            <td>@include('partials.status-badge', ['facility' => $facility])</td>
                            <td>{{ $facility->created_at->format('M d, Y') }}</td>
                            <td>
                                @if ($facility->isEditableByOwner())
                                    <a href="{{ route('resident.submissions.edit', $facility) }}">{{ $facility->status === 'rejected' ? 'Fix and resubmit' : 'Edit' }}</a>
                                @else
                                    <a href="{{ route('resident.update-requests.create', $facility) }}">Request update</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
