<ul class="features features-grid">
    @foreach (\App\Models\Facility::FEATURES as $field => $label)
        <li class="{{ $facility->$field ? 'yes' : 'no' }}">
            <span aria-hidden="true">{{ $facility->$field ? '✔' : '✖' }}</span>
            {{ $label }}: {{ $facility->$field ? 'Yes' : 'No' }}
        </li>
    @endforeach
</ul>
@if (! empty($facility->amenities))
    <p class="amenity-line">
        @foreach ($facility->amenities as $key)
            <span class="chip chip-static">{{ \App\Models\Facility::AMENITIES[$key] ?? $key }}</span>
        @endforeach
    </p>
@endif
