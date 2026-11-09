@extends('layouts.app')

@section('title', 'Edit submission')

@section('content')
    <p class="eyebrow">{{ $facility->status === 'rejected' ? 'FIX AND RESUBMIT' : 'EDIT PENDING SUBMISSION' }}</p>
    <h1>{{ $facility->name }}</h1>

    @if ($facility->status === 'rejected')
        <div class="callout callout-red" role="note">
            <strong>Why it was rejected:</strong> {{ $facility->admin_remarks ?: 'No reason was recorded.' }}
            <br>Correct the information below and resubmit. It goes back into the review queue.
        </div>
    @else
        <p class="lead">This submission is still waiting for review, so you can change it. Saving keeps it pending.</p>
    @endif

    @include('partials.facility-form', [
        'facility' => $facility,
        'action' => route('resident.submissions.update', $facility),
        'method' => 'PUT',
        'submitLabel' => $facility->status === 'rejected' ? 'Resubmit for review' : 'Save changes',
        'cancelUrl' => route('resident.submissions.index'),
    ])
@endsection
