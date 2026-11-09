{{--
    One form used for: a new submission, editing a pending/rejected submission,
    an update request for an approved facility, and an admin edit.

    Variables: $facility (model or null), $action, $method (POST or PUT),
               $submitLabel, $cancelUrl, $withReason (true for update requests).
--}}
@php
    $hasOld = session()->hasOldInput();
    $val = fn ($key, $default = '') => $hasOld ? old($key, '') : data_get($facility, $key, $default);
    $feature = fn ($key) => $hasOld ? (bool) old($key) : (bool) data_get($facility, $key, false);
    $chosenAmenities = $hasOld ? (array) old('amenities', []) : (array) data_get($facility, 'amenities', []);

    if ($hasOld) {
        $floorRows = array_values((array) old('floors', []));
    } elseif ($facility) {
        $floorRows = $facility->floors->map(fn ($f) => [
            'level' => $f->level, 'label' => $f->label, 'access' => $f->access, 'services' => $f->services,
        ])->values()->all();
    } else {
        $floorRows = [['level' => 0, 'label' => 'Ground floor', 'access' => 'ground', 'services' => '']];
    }

    $formConfig = [
        'floors' => $floorRows,
        'floorAccess' => \App\Models\Facility::FLOOR_ACCESS,
        'presets' => \App\Models\Facility::PRESETS,
        'featureKeys' => array_keys(\App\Models\Facility::FEATURES),
        'amenityKeys' => array_keys(\App\Models\Facility::AMENITIES),
        'center' => [15.4869, 120.9734],
    ];
@endphp

@include('partials.errors')

<form method="POST" action="{{ $action }}" class="stacked submission-form" id="facility-form">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    @if (empty($withReason))
        <section class="card form-section">
            <h2>Quick start</h2>
            <p class="meta">Pick a preset to pre-fill the category, usual features, services and floors. Then correct anything that does not match what you actually see.</p>
            <div class="preset-row" id="preset-row" role="group" aria-label="Presets">
                @foreach (\App\Models\Facility::PRESETS as $key => $preset)
                    <button type="button" class="chip" data-preset="{{ $key }}">{{ $preset['label'] }}</button>
                @endforeach
            </div>
        </section>
    @endif

    @if (! empty($withReason))
        <section class="card form-section">
            <h2>Why does this need to change?</h2>
            <p class="meta">An admin compares your changes with the current record before approving them.</p>
            <label for="reason">Reason for the update</label>
            <textarea id="reason" name="reason" rows="3" maxlength="500" required placeholder="Example: An elevator was installed last month.">{{ old('reason') }}</textarea>
            @error('reason') <p class="field-error">{{ $message }}</p> @enderror
        </section>
    @endif

    <section class="card form-section">
        <h2>Facility information</h2>
        <label for="name">Facility name</label>
        <input type="text" id="name" name="name" value="{{ $val('name') }}" required maxlength="150" placeholder="Enter the facility name">
        @error('name') <p class="field-error">{{ $message }}</p> @enderror

        <label for="category">Category</label>
        <select id="category" name="category" required>
            <option value="">Choose a category</option>
            @foreach (\App\Models\Facility::CATEGORIES as $category)
                <option value="{{ $category }}" @selected($val('category') === $category)>{{ $category }}</option>
            @endforeach
        </select>
        @error('category') <p class="field-error">{{ $message }}</p> @enderror

        <label for="address">Street address or location description</label>
        <input type="text" id="address" name="address" value="{{ $val('address') }}" required maxlength="255" placeholder="Street, barangay, city/municipality">
        @error('address') <p class="field-error">{{ $message }}</p> @enderror

        <div class="two-col">
            <div>
                <label for="hours">Opening hours (optional)</label>
                <input type="text" id="hours" name="hours" value="{{ $val('hours') }}" maxlength="120" placeholder="Mon to Fri, 8:00 AM to 5:00 PM">
                @error('hours') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="contact">Contact number (optional)</label>
                <input type="text" id="contact" name="contact" value="{{ $val('contact') }}" maxlength="60" placeholder="(044) 000-0000">
                @error('contact') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="card form-section">
        <h2>Pin the facility on the map</h2>
        <p class="meta">Search for a place, click the map, drag the pin, or use your current location.</p>
        <div class="map-tools">
            <input type="search" id="place-search" placeholder="Search a place or street" aria-label="Search a place or street" autocomplete="off">
            <button type="button" class="btn btn-light" id="place-search-btn">Search</button>
            <button type="button" class="btn btn-light" id="use-location">Use my current location</button>
        </div>
        <p id="location-status" class="meta" aria-live="polite">No map location selected yet.</p>
        <div id="pick-map" class="pick-map" role="region" aria-label="Choose facility location on map"></div>
        <input type="hidden" id="latitude" name="latitude" value="{{ $val('latitude') }}">
        <input type="hidden" id="longitude" name="longitude" value="{{ $val('longitude') }}">
        @error('latitude') <p class="field-error">{{ $message }}</p> @enderror
        @error('longitude') <p class="field-error">{{ $message }}</p> @enderror
        <p class="coordinate-readout" id="coordinate-readout">Coordinates: not selected</p>
    </section>

    <section class="card form-section">
        <h2>Accessibility features</h2>
        <p class="meta">Select only the features that are available at this facility right now.</p>
        <div class="feature-check-grid">
            @foreach (\App\Models\Facility::FEATURES as $field => $label)
                <label class="feature-check">
                    <input type="checkbox" name="{{ $field }}" value="1" @checked($feature($field))>
                    <span><span aria-hidden="true">{{ \App\Models\Facility::FEATURE_ICONS[$field] }}</span> {{ $label }}</span>
                </label>
            @endforeach
        </div>

        <h3 class="sub-heading">Services and support</h3>
        <div class="feature-check-grid">
            @foreach (\App\Models\Facility::AMENITIES as $key => $label)
                <label class="feature-check">
                    <input type="checkbox" name="amenities[]" value="{{ $key }}" @checked(in_array($key, $chosenAmenities, true))>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('amenities.*') <p class="field-error">{{ $message }}</p> @enderror
    </section>

    <section class="card form-section">
        <h2>Getting there</h2>
        <p class="meta">What is the path to the entrance like? This helps people with wheelchairs, canes, or strollers plan ahead.</p>
        <div class="two-col">
            <div>
                <label for="surface">Surface of the approach</label>
                <select id="surface" name="surface">
                    <option value="">Not sure</option>
                    @foreach (\App\Models\Facility::SURFACES as $surface)
                        <option value="{{ $surface }}" @selected($val('surface') === $surface)>{{ $surface }}</option>
                    @endforeach
                </select>
                @error('surface') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="slope">Slope of the approach</label>
                <select id="slope" name="slope">
                    <option value="">Not sure</option>
                    @foreach (\App\Models\Facility::SLOPES as $slope)
                        <option value="{{ $slope }}" @selected($val('slope') === $slope)>{{ $slope }}</option>
                    @endforeach
                </select>
                @error('slope') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="card form-section">
        <h2>Floors and levels</h2>
        <p class="meta">List each floor, what is on it, and how people reach it. Level 0 is the ground floor, 1 is the second floor, and -1 is a basement.</p>
        @error('floors') <p class="field-error">{{ $message }}</p> @enderror
        @error('floors.*.level') <p class="field-error">{{ $message }}</p> @enderror
        @error('floors.*.label') <p class="field-error">{{ $message }}</p> @enderror
        @error('floors.*.access') <p class="field-error">{{ $message }}</p> @enderror
        <div id="floor-rows" class="floor-rows"></div>
        <button type="button" class="btn btn-light" id="add-floor">+ Add a floor</button>
    </section>

    <section class="card form-section">
        <h2>Notes</h2>
        <label for="notes">Additional details (optional)</label>
        <textarea id="notes" name="notes" rows="4" maxlength="1000" placeholder="Describe entrance conditions, pathway access, or other useful details.">{{ $val('notes') }}</textarea>
        @error('notes') <p class="field-error">{{ $message }}</p> @enderror
    </section>

    <div class="form-actions">
        <button type="submit" class="btn">{{ $submitLabel }}</button>
        <a class="text-link" href="{{ $cancelUrl }}">Cancel</a>
    </div>
</form>

@push('scripts')
    <script>window.FACILITY_FORM = {{ Illuminate\Support\Js::from($formConfig) }};</script>
    <script src="{{ \App\Support\Asset::url('js/anywhere-core.js') }}"></script>
    <script src="{{ \App\Support\Asset::url('js/facility-form.js') }}"></script>
@endpush
