@extends('layouts.app')

@section('title', 'Update requests')

@section('content')
    <h1>Update requests</h1>
    <p class="lead">Residents ask to change facilities that are already public. Approving applies the change to the facility record.</p>

    @include('partials.errors')

    <nav class="tabs" aria-label="Filter by status">
        @foreach (\App\Models\Facility::STATUSES as $tab)
            <a href="{{ route('admin.update-requests.index', ['status' => $tab]) }}"
               @if ($status === $tab) aria-current="page" @endif>
                {{ ucfirst($tab) }} ({{ $counts[$tab] ?? 0 }})
            </a>
        @endforeach
    </nav>

    @if ($requests->isEmpty())
        <div class="empty">No {{ $status }} update requests right now.</div>
    @else
        <div class="stack">
            @foreach ($requests as $req)
                @php $diff = $req->differences(); @endphp
                <article class="card">
                    <h3>{{ $req->facility->name }} <span class="badge badge-{{ $req->status }}">{{ ucfirst($req->status) }}</span></h3>
                    <p class="meta">Requested by {{ $req->requester->name }} on {{ $req->created_at->format('M d, Y') }}</p>
                    <p><strong>Reason:</strong> {{ $req->reason }}</p>

                    @if (empty($diff))
                        <p class="meta">The facility already matches this request, so nothing would change.</p>
                    @else
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Field</th><th>Current record</th><th>Proposed change</th></tr></thead>
                                <tbody>
                                    @foreach ($diff as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td class="diff-old">{{ $row['old'] }}</td>
                                            <td class="diff-new">{{ $row['new'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if ($req->admin_remarks)
                        <p><strong>Admin remarks:</strong> {{ $req->admin_remarks }}</p>
                    @endif

                    @if ($req->status === 'pending')
                        <form method="POST" action="{{ route('admin.update-requests.update', $req) }}" class="stacked">
                            @csrf
                            @method('PATCH')
                            <label for="remarks-{{ $req->id }}">Remarks (required when rejecting)</label>
                            <textarea id="remarks-{{ $req->id }}" name="admin_remarks" rows="2" maxlength="500"></textarea>
                            <div class="inline-actions">
                                <button type="submit" name="status" value="approved" class="btn">Approve and apply</button>
                                <button type="submit" name="status" value="rejected" class="btn btn-danger">Reject</button>
                            </div>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>

        @include('partials.pager', ['paginator' => $requests])
    @endif
@endsection
