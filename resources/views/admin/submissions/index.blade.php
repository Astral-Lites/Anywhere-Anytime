@extends('layouts.app')

@section('title', 'Review submissions')

@section('content')
    <h1>Review submissions</h1>
    <p class="lead">Approve a facility to publish it in the directory, or reject it with a reason.</p>

    @include('partials.errors')

    <nav class="tabs" aria-label="Filter by status">
        @foreach (\App\Models\Facility::STATUSES as $tab)
            <a href="{{ route('admin.submissions.index', ['status' => $tab]) }}"
               @if ($status === $tab) aria-current="page" @endif>
                {{ ucfirst($tab) }} ({{ $counts[$tab] ?? 0 }})
            </a>
        @endforeach
    </nav>

    @if ($facilities->isEmpty())
        <div class="empty">No {{ $status }} submissions right now.</div>
    @else
        <div class="stack">
            @foreach ($facilities as $facility)
                <article class="card">
                    <h3>{{ $facility->name }} @include('partials.status-badge', ['facility' => $facility])</h3>
                    <p class="meta">
                        {{ $facility->category }} &middot; {{ $facility->address }}<br>
                        Submitted by {{ $facility->submitter->name }} on {{ $facility->created_at->format('M d, Y') }}
                    </p>

                    @include('partials.features', ['facility' => $facility])

                    @if ($facility->notes)
                        <p><strong>Notes from resident:</strong> {{ $facility->notes }}</p>
                    @endif

                    @if ($facility->admin_remarks)
                        <p><strong>Admin remarks:</strong> {{ $facility->admin_remarks }}</p>
                    @endif

                    @if ($facility->floors->isNotEmpty())
                        <p class="meta"><strong>Floors:</strong>
                            @foreach ($facility->floors as $floor)
                                L{{ $floor->level }} {{ $floor->label }} ({{ \App\Models\Facility::FLOOR_ACCESS[$floor->access] ?? $floor->access }})@if (! $loop->last); @endif
                            @endforeach
                        </p>
                    @endif
                    <p><a class="text-link" href="{{ route('admin.facilities.edit', $facility) }}">Edit record</a></p>

                    <form method="POST" action="{{ route('admin.submissions.update', $facility) }}" class="stacked">
                        @csrf
                        @method('PATCH')

                        <label for="remarks-{{ $facility->id }}">Remarks (required when rejecting)</label>
                        <textarea id="remarks-{{ $facility->id }}" name="admin_remarks" rows="2" maxlength="500"></textarea>

                        <div class="inline-actions">
                            <button type="submit" name="status" value="approved" class="btn">Approve</button>
                            <button type="submit" name="status" value="rejected" class="btn btn-danger">Reject</button>
                        </div>
                    </form>
                </article>
            @endforeach
        </div>

        @include('partials.pager', ['paginator' => $facilities])
    @endif
@endsection
