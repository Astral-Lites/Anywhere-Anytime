<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Anywhere, Anytime')</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    {{-- Atkinson Hyperlegible is designed for readability. If it cannot load (offline demo), the system font is used. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/anywhere-ui.css') }}">
    @stack('head')

    <style>
        :root {
            --ink: #1d2b33;
            --muted: #51636d;
            --teal: #0e6e6e;
            --teal-dark: #0a5252;
            --surface: #eef5f4;
            --page: #f7faf9;
            --line: #d3e0de;
            --white: #ffffff;
            --amber-bg: #fff3d6;  --amber: #7a4e00;
            --green-bg: #e3f3e8;  --green: #1b5e35;
            --red-bg: #fbe5e5;    --red: #8f2626;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Atkinson Hyperlegible", system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
            font-size: 1.05rem;
            line-height: 1.55;
            color: var(--ink);
            background: var(--page);
        }
        a { color: var(--teal-dark); }
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible {
            outline: 3px solid #f2a900;
            outline-offset: 2px;
        }

        .topbar { background: var(--teal); color: var(--white); }
        .topbar-inner {
            max-width: 1040px; margin: 0 auto; padding: .75rem 1rem;
            display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1.25rem;
        }
        .brand { font-weight: 700; font-size: 1.2rem; color: var(--white); text-decoration: none; margin-right: auto; }
        .topbar a, .link-button { color: var(--white); }
        .topbar nav { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem 1.1rem; }
        .link-button {
            background: none; border: 0; padding: 0; font: inherit; text-decoration: underline; cursor: pointer;
        }

        main { max-width: 1240px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        h1 { font-size: 1.75rem; margin: 0 0 .25rem; }
        h2 { font-size: 1.25rem; margin: 2rem 0 .75rem; }
        .lead { color: var(--muted); margin: 0 0 1.5rem; }

        .flash { background: var(--green-bg); color: var(--green); border: 1px solid #b9dcc5; padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1.25rem; }
        .errors { background: var(--red-bg); color: var(--red); border: 1px solid #efc2c2; padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1.25rem; }
        .errors ul { margin: .25rem 0 0; padding-left: 1.2rem; }
        .field-error { color: var(--red); margin: .25rem 0 0; font-size: .95rem; }

        .card { background: var(--white); border: 1px solid var(--line); border-radius: 8px; padding: 1.1rem 1.25rem; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; }
        .stat strong { display: block; font-size: 2rem; line-height: 1.1; }
        .stat span { color: var(--muted); }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; }
        .card h3 { margin: 0 0 .15rem; font-size: 1.15rem; }
        .meta { color: var(--muted); margin: 0 0 .5rem; font-size: .95rem; }
        .stack > * + * { margin-top: 1rem; }

        form.stacked label { display: block; font-weight: 700; margin: 1rem 0 .25rem; }
        input[type=text], input[type=email], input[type=password], select, textarea {
            width: 100%; padding: .55rem .65rem; font: inherit; color: var(--ink);
            border: 1px solid #8aa0a6; border-radius: 6px; background: var(--white);
        }
        fieldset { border: 1px solid var(--line); border-radius: 6px; padding: .5rem 1rem 1rem; margin: 1.25rem 0 0; }
        legend { font-weight: 700; padding: 0 .35rem; }
        .check { display: flex; align-items: center; gap: .6rem; margin-top: .6rem; font-weight: 400 !important; }
        .check input { width: 1.2rem; height: 1.2rem; }

        .btn {
            display: inline-block; font: inherit; font-weight: 700; padding: .6rem 1.1rem; margin-top: 1rem;
            background: var(--teal); color: var(--white); border: 2px solid var(--teal); border-radius: 6px;
            cursor: pointer; text-decoration: none;
        }
        .btn:hover { background: var(--teal-dark); border-color: var(--teal-dark); }
        .btn-light { background: var(--white); color: var(--teal-dark); }
        .btn-light:hover { background: var(--surface); color: var(--teal-dark); }
        .btn-danger { background: var(--white); color: var(--red); border-color: var(--red); }
        .btn-danger:hover { background: var(--red-bg); border-color: var(--red); }

        .badge { display: inline-block; padding: .1rem .6rem; border-radius: 999px; font-size: .9rem; font-weight: 700; }
        .badge-pending { background: var(--amber-bg); color: var(--amber); }
        .badge-approved { background: var(--green-bg); color: var(--green); }
        .badge-rejected { background: var(--red-bg); color: var(--red); }

        .features { list-style: none; margin: .5rem 0 0; padding: 0; }
        .features li { padding: .1rem 0; }
        .features .yes { color: var(--green); font-weight: 700; }
        .features .no { color: var(--muted); }

        table { width: 100%; border-collapse: collapse; background: var(--white); border: 1px solid var(--line); }
        th, td { text-align: left; padding: .65rem .75rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { background: var(--surface); }
        .table-wrap { overflow-x: auto; }

        .tabs { display: flex; flex-wrap: wrap; gap: .5rem; margin: 1rem 0 1.25rem; }
        .tabs a { padding: .4rem .9rem; border: 1px solid var(--line); border-radius: 6px; background: var(--white); text-decoration: none; }
        .tabs a[aria-current="page"] { background: var(--teal); color: var(--white); border-color: var(--teal); }

        .filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; align-items: end; }
        .pager { display: flex; gap: 1rem; align-items: center; justify-content: center; margin-top: 1.5rem; }
        .pager span { color: var(--muted); }
        .empty { background: var(--white); border: 1px dashed #8aa0a6; border-radius: 8px; padding: 1.5rem; text-align: center; color: var(--muted); }
        .auth-box { max-width: 460px; margin: 1rem auto; }
        .inline-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
        .inline-actions .btn { margin-top: .5rem; }
        .eyebrow { color: var(--teal); font-size: .78rem; font-weight: 700; letter-spacing: .12em; margin: 0 0 .35rem; }
        .page-heading { display:flex; justify-content:space-between; align-items:end; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
        .page-heading .btn { margin:0 0 1.5rem; }
        .filter-card { margin-bottom:1.4rem; }
        .filter-submit { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
        .filter-submit .btn { margin:0; }
        .text-link { border:0; background:none; color:var(--teal-dark); font:inherit; text-decoration:underline; cursor:pointer; padding:.25rem 0; }
        .feature-filter { margin-top:1rem; border-top:1px solid var(--line); padding-top:.8rem; }
        .feature-filter summary { cursor:pointer; font-weight:700; }
        .feature-options { display:flex; flex-wrap:wrap; gap:.4rem 1.4rem; padding-top:.5rem; }
        .map-heading { display:flex; justify-content:space-between; align-items:end; gap:1rem; }
        .map-heading h2 { margin:0; }
        .result-count,.category-pill { background:var(--surface); border-radius:999px; padding:.25rem .7rem; font-size:.9rem; }
        .facility-map,.pick-map { width:100%; height:440px; border:1px solid var(--line); border-radius:10px; z-index:1; background:#e8eeee; }
        .facility-map { margin-top:.8rem; }
        .pick-map { height:380px; margin-top:.7rem; }
        .map-legend { display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap; color:var(--muted); font-size:.85rem; padding:.45rem .15rem; }
        .legend-dot { display:inline-block; width:.65rem; height:.65rem; border-radius:50%; background:var(--teal); margin-right:.3rem; }
        .section-heading { margin-top:1.8rem; }
        .facility-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(270px,1fr)); gap:1rem; }
        .facility-card { background:var(--white); border:1px solid var(--line); border-radius:9px; padding:1rem 1.1rem; scroll-margin-top:1rem; }
        .facility-card h3 { margin:.5rem 0 .15rem; }
        .facility-card-top { display:flex; justify-content:space-between; align-items:center; gap:.5rem; }
        .verified-label { color:var(--green); font-size:.85rem; font-weight:700; }
        .facility-notes { border-top:1px solid var(--line); padding-top:.6rem; }
        .map-tools { display:flex; flex-wrap:wrap; align-items:center; gap:.8rem; }
        .map-tools .btn { margin:0; }
        .coordinate-readout { color:var(--muted); font-size:.9rem; margin:.5rem 0 0; }
        .submission-form { display:grid; gap:1rem; }
        .form-section h2 { margin:0 0 .25rem; }
        .form-section > label { display:block; font-weight:700; margin:1rem 0 .25rem; }
        .feature-check-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:.7rem; margin-top:1rem; }
        .feature-check { display:flex; align-items:center; gap:.6rem; border:1px solid var(--line); border-radius:7px; padding:.7rem; cursor:pointer; }
        .feature-check input { width:1.15rem; height:1.15rem; }
        .form-actions { display:flex; align-items:center; gap:1rem; }
        .form-actions .btn { margin:0; }
        @media(max-width:640px) { .facility-map {height:360px;} .pick-map {height:330px;} .map-heading {align-items:start; flex-direction:column;} }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('dashboard') }}">Anywhere, Anytime</a>
            <nav aria-label="Main">
                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.home') }}">Dashboard</a>
                        <a href="{{ route('admin.submissions.index') }}">Review submissions</a>
                        <a href="{{ route('admin.update-requests.index') }}">Update requests</a>
                        <a href="{{ route('admin.reports.index') }}">Reports</a>
                    @else
                        <a href="{{ route('resident.home') }}">Dashboard</a>
                        <a href="{{ route('resident.submissions.index') }}">My submissions</a>
                        <a href="{{ route('resident.submissions.create') }}">Submit a facility</a>
                    @endif
                    <a href="{{ route('directory') }}">Explore map</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="link-button">Log out ({{ auth()->user()->name }})</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">Log in</a>
                    <a href="{{ route('register') }}">Create account</a>
                @endauth
            </nav>
        </div>
    </header>

    <main @hasSection('fullwidth') class="main-wide" @endif>
        @if (session('status'))
            <div class="flash" role="status">{{ session('status') }}</div>
        @endif

        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
