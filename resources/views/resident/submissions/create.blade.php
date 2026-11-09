@extends('layouts.app')

@section('title', 'Add a facility')

@section('content')
    <p class="eyebrow">COMMUNITY CONTRIBUTION</p>
    <h1>Add a community facility</h1>
    <p class="lead">Share a facility and its accessibility details. An administrator will review the submission before it appears publicly.</p>

    @include('partials.facility-form', [
        'facility' => null,
        'action' => route('resident.submissions.store'),
        'method' => 'POST',
        'submitLabel' => 'Submit for review',
        'cancelUrl' => route('resident.home'),
    ])
@endsection
