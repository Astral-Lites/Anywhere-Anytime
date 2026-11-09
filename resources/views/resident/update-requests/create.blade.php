@extends('layouts.app')

@section('title', 'Request an update')

@section('content')
    <p class="eyebrow">COMMUNITY UPDATE</p>
    <h1>Request an update: {{ $facility->name }}</h1>
    <p class="lead">This facility is already public, so it cannot be edited directly. Change what is out of date and send it. An admin reviews the difference and approves or rejects it.</p>

    @if ($hasPending)
        <div class="callout callout-amber" role="note">You already have a pending update request for this facility. Wait for the review before sending another.</div>
    @else
        @include('partials.facility-form', [
            'facility' => $facility,
            'action' => route('resident.update-requests.store', $facility),
            'method' => 'POST',
            'submitLabel' => 'Send update request',
            'cancelUrl' => route('directory'),
            'withReason' => true,
        ])
    @endif
@endsection
