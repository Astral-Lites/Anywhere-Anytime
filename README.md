# Anywhere, Anytime: Community Accessibility Mapping and Facility Information System (Early Prototype)

**Course:** ITE 116 - Integrative and Programming Technologies (Midterm Requirement)

**Group members:**
- Josef Lance N. Acorda
- Justin Joeff L. Lacerona

## Description

Anywhere, Anytime helps people find out whether community facilities have the accessibility features they need, such as ramps, accessible entrances, accessible restrooms, and accessible parking. Residents can explore approved facilities on an interactive OpenStreetMap map, inspect accessibility features, and submit new facilities by placing a pin on the map. An admin reviews each submission before it is published. This is an early prototype built with Laravel and Blade.

## User roles

| Role | What the role can do in the prototype |
| --- | --- |
| **Admin** | Log in and log out. See the admin dashboard with submission counts. Review submissions by status, approve a submission, or reject it with a reason. Search the facility directory and view approved facilities on the interactive map. |
| **Resident** | Register, log in, and log out. See the resident dashboard. Submit a facility with its accessibility features and map coordinates, including the option to use the browser location. See "My submissions" with the review status and rejection reason. Search the facility directory and view approved facilities on the interactive map. |

Access rules:
- Each role is sent to its own dashboard after login.
- A resident who types an admin URL (for example `/admin/home`), or an admin who types a resident URL, gets a **403 Forbidden** page.
- A visitor who is not logged in and opens any protected page is redirected to the login page.

## Core feature: facility submission and review

1. A resident submits a facility (name, category, address, accessibility features, notes). The server validates the input and saves it as **pending**, linked to the logged-in resident.
2. The resident sees it under **My submissions**.
3. The admin sees it under **Review submissions** and approves it, or rejects it with a required reason.
4. Approved facilities appear in the **Explore map** page, where they can be searched, filtered, viewed on an interactive map, and opened for accessibility details.

## Demo accounts

| Role | Email | Password |
| --- | --- | --- |
| Admin | admin@example.com | password |
| Resident | resident@example.com | password |

## Setup steps (from a fresh clone)

Requirements: PHP 8.2 or higher, Composer, and Git. The default database is SQLite, so no database server is needed.

```bash
git clone <repository-url>
cd <project-folder>
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Then open http://127.0.0.1:8000 in a browser.

Notes:
- If Laravel asks to create `database/database.sqlite`, answer **yes**. You can also create the empty file yourself before running `migrate`.
- To use MySQL instead, edit the `DB_*` values in `.env` and create the database first.
- This prototype does not use Vite, so `npm install` and `npm run dev` are not needed.
- Do not commit `vendor/`, `node_modules/`, or `.env`. The default `.gitignore` already excludes them.


## Map prototype notes

- The map uses Leaflet and OpenStreetMap tiles. An internet connection is required for map tiles and the Leaflet CDN assets.
- Residents choose a facility location by clicking the map or using the browser's current-location permission. The selected coordinates are saved with the submission.
- Only approved facilities appear in the public map. Pending and rejected submissions remain private to the submitter and administrators.
- Seeded demo facilities use illustrative coordinates near Cabanatuan City and are fictional examples, not verified real facilities. Replace them with verified locations before presenting the map as real community data.
- Directions on the map are a guidance aid built on OpenStreetMap routing, with a straight-line fallback if the service is unreachable. They do not check for steps, kerbs, or curb ramps, and they are not turn-by-turn navigation for the thesis scope.
- Floors are described by the community (level, name, how it is reached). The map does not draw indoor plans or route inside buildings.
- Elevation comes from a free public elevation service at about 90 m resolution. Ramp gradients are steeper than the profile shows.
- Place search uses OpenStreetMap Nominatim; keep usage light during demos.

## Features added in this version

- Residents edit pending submissions, fix and resubmit rejected ones, and delete pending or rejected ones.
- Approved facilities are changed only through an update request. The admin sees a field-by-field comparison before approving.
- Admins can edit any facility record and download an accessibility gap report as CSV.
- The submission form has presets, 10 accessibility features, services, approach surface and slope, opening hours, and a floor editor.
- The explore map has filters, accessibility profiles ("I need access for"), a "you are here" marker, distance rings, a floor picker, directions, an elevation profile, and map styles.

## Tests

- `node tests/core.test.js` checks distance, matching, route parsing, and slope maths.
- The map logic was checked in headless Chromium with a stand-in for Leaflet, because the test machine had no internet access. Run the app once on your own machine to confirm the real tiles and routing.
