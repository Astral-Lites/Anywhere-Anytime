@extends('layouts.app')

@section('title', 'Accessibility gap report')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">REPORT</p>
            <h1>Accessibility gap report</h1>
            <p class="lead">Based on {{ $total }} approved {{ $total === 1 ? 'facility' : 'facilities' }}. Pending and rejected submissions are not counted.</p>
        </div>
        <a class="btn" href="{{ route('admin.reports.export') }}">Download CSV</a>
    </div>

    @if ($total === 0)
        <div class="empty">There are no approved facilities yet, so there are no gaps to report.</div>
    @else
        <h2>Missing features</h2>
        <p class="meta">How many approved facilities do not have each feature.</p>
        <div class="card">
            @foreach ($gaps as $field => $gap)
                <div class="gap-row">
                    <div class="gap-label">{{ $gap['label'] }}</div>
                    <div class="gap-bar" role="img" aria-label="{{ $gap['count'] }} of {{ $total }} facilities are missing this feature">
                        <span style="width: {{ $gap['percent'] }}%"></span>
                    </div>
                    <div class="gap-count">{{ $gap['count'] }} of {{ $total }} ({{ $gap['percent'] }}%)</div>
                </div>
            @endforeach
        </div>

        <h2>Facilities missing each feature</h2>
        <div class="stack">
            @foreach ($gaps as $field => $gap)
                <details class="card">
                    <summary><strong>{{ $gap['label'] }}</strong> &middot; missing in {{ $gap['count'] }}</summary>
                    @if ($gap['missing']->isEmpty())
                        <p class="meta">Every approved facility has this feature.</p>
                    @else
                        <ul>
                            @foreach ($gap['missing'] as $facility)
                                <li>{{ $facility->name }} <span class="meta">({{ $facility->category }})</span></li>
                            @endforeach
                        </ul>
                    @endif
                </details>
            @endforeach
        </div>

        <h2>By category</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Category</th><th>Facilities</th><th>Average accessibility score</th></tr></thead>
                <tbody>
                    @foreach ($byCategory as $category => $row)
                        <tr><td>{{ $category }}</td><td>{{ $row['count'] }}</td><td>{{ $row['average'] }}%</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <h2>Needs attention</h2>
        <div class="two-col">
            <div class="card">
                <h3>Lowest scores</h3>
                <ul>
                    @foreach ($lowest as $facility)
                        <li>{{ $facility->name }}: {{ $facility->accessibility_score }}%</li>
                    @endforeach
                </ul>
            </div>
            <div class="card">
                <h3>Upper floors reachable by stairs only</h3>
                @if ($stairsOnly->isEmpty())
                    <p class="meta">None.</p>
                @else
                    <ul>
                        @foreach ($stairsOnly as $facility)
                            <li>{{ $facility->name }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
@endsection
