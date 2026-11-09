@extends('layouts.app')

@section('title', 'My submissions')

@section('content')
    <h1>My submissions</h1>
    <p class="lead">Facilities you submitted and where each one is in the review.</p>

    @include('partials.errors')

    <div class="callout" role="note">
        <strong>What you can do with each status:</strong>
        <span class="badge badge-pending">Pending</span> edit or delete it &middot;
        <span class="badge badge-rejected">Rejected</span> fix it and resubmit, or delete it &middot;
        <span class="badge badge-approved">Approved</span> it is public, so send an update request from the map.
    </div>

    @if ($facilities->isEmpty())
        <div class="empty">
            Nothing here yet. <a href="{{ route('resident.submissions.create') }}">Submit a facility</a> to get started.
        </div>
    @else
        <div class="stack">
            @foreach ($facilities as $facility)
                <article class="card">
                    <h3>{{ $facility->name }} @include('partials.status-badge', ['facility' => $facility])</h3>
                    <p class="meta">{{ $facility->category }} &middot; {{ $facility->address }} &middot; Submitted {{ $facility->created_at->format('M d, Y') }}</p>

                    <p class="status-note">
                        @if ($facility->status === 'pending')
                            Waiting for an admin to review it. You can still make changes.
                        @elseif ($facility->status === 'approved')
                            Approved{{ $facility->reviewed_at ? ' on ' . $facility->reviewed_at->format('M d, Y') : '' }} and visible on the map.
                        @else
                            Rejected{{ $facility->reviewed_at ? ' on ' . $facility->reviewed_at->format('M d, Y') : '' }}. Fix the problem below and resubmit.
                        @endif
                    </p>

                    @include('partials.features', ['facility' => $facility])

                    @if ($facility->notes)
                        <p><strong>Your notes:</strong> {{ $facility->notes }}</p>
                    @endif

                    @if ($facility->status === 'rejected' && $facility->admin_remarks)
                        <p class="callout callout-red"><strong>Reason for rejection:</strong> {{ $facility->admin_remarks }}</p>
                    @endif

                    <div class="inline-actions">
                        @if ($facility->isEditableByOwner())
                            <a class="btn btn-light" href="{{ route('resident.submissions.edit', $facility) }}">
                                {{ $facility->status === 'rejected' ? 'Edit and resubmit' : 'Edit' }}
                            </a>
                            <form method="POST" action="{{ route('resident.submissions.destroy', $facility) }}"
                                  onsubmit="return confirm('Delete this submission? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        @else
                            <a class="btn btn-light" href="{{ route('resident.update-requests.create', $facility) }}">Request an update</a>
                            <a class="btn btn-light" href="{{ route('directory') }}?facility={{ $facility->id }}">View on map</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @include('partials.pager', ['paginator' => $facilities])
    @endif

    <h2>My update requests</h2>
    @if ($updateRequests->isEmpty())
        <div class="empty">You have not sent any update requests. Open an approved facility on the map and choose "Request an update".</div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Facility</th><th>Reason</th><th>Status</th><th>Sent</th></tr></thead>
                <tbody>
                    @foreach ($updateRequests as $req)
                        <tr>
                            <td>{{ $req->facility->name }}</td>
                            <td>{{ $req->reason }}
                                @if ($req->status === 'rejected' && $req->admin_remarks)
                                    <br><strong>Admin reply:</strong> {{ $req->admin_remarks }}
                                @endif
                            </td>
                            <td><span class="badge badge-{{ $req->status }}">{{ ucfirst($req->status) }}</span></td>
                            <td>{{ $req->created_at->format('M d, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
