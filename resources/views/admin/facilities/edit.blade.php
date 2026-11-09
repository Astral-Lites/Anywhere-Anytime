@extends('layouts.app')

@section('title', 'Edit facility')

@section('content')
    <p class="eyebrow">ADMIN EDIT</p>
    <h1>Edit {{ $facility->name }} @include('partials.status-badge', ['facility' => $facility])</h1>
    <p class="lead">Changes are saved immediately and do not change the review status.</p>

    @include('partials.facility-form', [
        'facility' => $facility,
        'action' => route('admin.facilities.update', $facility),
        'method' => 'PUT',
        'submitLabel' => 'Save changes',
        'cancelUrl' => route('admin.submissions.index', ['status' => $facility->status]),
    ])
@endsection
