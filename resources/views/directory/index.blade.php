@extends('layouts.app')

@section('title', 'Explore map')
@section('fullwidth', '1')

@section('content')
<div class="aa-app" id="aa-app" data-theme="day">

    <div id="facility-map" class="aa-map" role="application" aria-label="Interactive map of approved community facilities"></div>

    {{-- Top banner while following a route --}}
    <div class="aa-nav" id="aa-nav" hidden role="region" aria-label="Directions">
        <span class="nav-icon" id="nav-icon" aria-hidden="true">↑</span>
        <div class="nav-body">
            <strong id="nav-text" aria-live="polite"></strong>
            <span id="nav-dist"></span>
        </div>
        <div class="nav-buttons">
            <button type="button" class="chip" id="nav-reroute" hidden>Recalculate</button>
            <button type="button" class="chip" id="nav-voice" aria-pressed="false">🔊 Voice</button>
            <button type="button" class="chip" id="nav-end">End</button>
        </div>
    </div>

    <div class="aa-hud" id="aa-hud" role="status"></div>

    {{-- Left panel: search, filters, list --}}
    <aside class="aa-panel aa-side" id="aa-side" aria-label="Find facilities">
        <div class="side-head">
            <div>
                <p class="eyebrow">COMMUNITY ACCESSIBILITY MAP</p>
                <h1>Explore facilities</h1>
            </div>
            @if (! auth()->user()->isAdmin())
                <a class="btn btn-small" href="{{ route('resident.submissions.create') }}">+ Add</a>
            @endif
        </div>

        <label for="f-q" class="sr-only">Search a facility or address</label>
        <input type="search" id="f-q" placeholder="Search a facility or address" autocomplete="off">

        <div class="side-grid">
            <div>
                <label for="f-need">I need access for</label>
                <select id="f-need"><option value="">Everyone (general score)</option></select>
            </div>
            <div>
                <label for="f-category">Category</label>
                <select id="f-category"><option value="">All categories</option></select>
            </div>
        </div>

        <details class="side-more">
            <summary>Filter by feature</summary>
            <div class="chip-wrap" id="f-features"></div>
            <div class="side-grid">
                <div>
                    <label for="f-radius">Distance</label>
                    <select id="f-radius">
                        <option value="0">Any distance</option>
                        <option value="0.5">Within 500 m</option>
                        <option value="1">Within 1 km</option>
                        <option value="3">Within 3 km</option>
                        <option value="5">Within 5 km</option>
                    </select>
                </div>
                <div>
                    <label for="f-sort">Sort by</label>
                    <select id="f-sort">
                        <option value="auto">Smart (nearest or best match)</option>
                        <option value="near">Nearest</option>
                        <option value="match">Best match</option>
                        <option value="score">Highest access score</option>
                        <option value="name">Name</option>
                    </select>
                </div>
            </div>
            <label class="check"><input type="checkbox" id="f-min"> Only show good matches (70% or more)</label>
        </details>

        <div class="list-head">
            <span id="aa-count" aria-live="polite"></span>
            <button type="button" class="text-link" id="f-reset">Reset filters</button>
        </div>
        <ol class="aa-list" id="aa-list"></ol>
    </aside>

    {{-- Right panel: facility details, floors, directions --}}
    <aside class="aa-panel aa-detail" id="aa-detail" hidden aria-label="Facility details"></aside>

    {{-- Map controls --}}
    <div class="aa-controls">
        <button type="button" class="ctl" id="aa-locate" title="Show where I am" aria-label="Show where I am">📍<span>Locate me</span></button>
        <button type="button" class="ctl" id="aa-manual" aria-pressed="false" title="Place myself on the map" aria-label="Set my position by tapping the map">🎯<span>Set position</span></button>
        <button type="button" class="ctl" id="aa-follow" aria-pressed="false" title="Keep the map centered on me" aria-label="Follow my position">🧭<span>Follow</span></button>
        <div class="ctl-layers">
            <button type="button" class="ctl" id="aa-layers-btn" aria-expanded="false" aria-controls="aa-layers" aria-label="Map style">🗺️<span>Map style</span></button>
            <div class="aa-layers" id="aa-layers" hidden>
                <label><input type="radio" name="basemap" value="explorer"> Explorer</label>
                <label><input type="radio" name="basemap" value="night"> Night</label>
                <label><input type="radio" name="basemap" value="satellite"> Satellite</label>
                <label><input type="radio" name="basemap" value="classic" checked> Classic OSM (default)</label>
                <label class="layers-sep"><input type="checkbox" id="aa-radar" checked> Distance rings</label>
            </div>
        </div>
        <span class="ctl-status" id="aa-locate-status" aria-live="polite"></span>
    </div>

    <button type="button" class="aa-side-toggle" id="aa-side-toggle" aria-expanded="false" aria-controls="aa-side">☰ Facilities</button>
    <div class="aa-toast" id="aa-toast" role="status" aria-live="polite" hidden></div>
</div>

<noscript>
    <div class="callout" style="margin:1rem">The interactive map needs JavaScript. Here is a plain list of approved facilities instead.</div>
    <ul style="margin:1rem">
        @foreach ($points as $p)
            <li><strong>{{ $p['name'] }}</strong> ({{ $p['category'] }}), {{ $p['address'] }}. Accessibility score {{ $p['score'] }}%.</li>
        @endforeach
    </ul>
</noscript>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/anywhere-map.css') }}">
@endpush

@push('scripts')
    <script>window.AA_DATA = {{ Illuminate\Support\Js::from(['points' => $points, 'config' => $config]) }};</script>
    <script src="{{ \App\Support\Asset::url('js/anywhere-core.js') }}"></script>
    <script src="{{ \App\Support\Asset::url('js/anywhere-map.js') }}"></script>
@endpush
