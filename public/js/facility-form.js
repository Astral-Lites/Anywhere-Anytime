/*
 * Facility form: presets, floors editor, and the map pin picker (with place search).
 * Used by the submit, edit, update-request, and admin edit pages.
 */
(function () {
    'use strict';

    var CFG = window.FACILITY_FORM;
    var form = document.getElementById('facility-form');
    if (!CFG || !form) return;
    var AA = window.AA;
    var esc = AA.escapeHtml;

    // ---- floors editor ----------------------------------------------------------------
    var rowsBox = document.getElementById('floor-rows');
    var nextIndex = 0;

    function accessOptions(selected) {
        return Object.keys(CFG.floorAccess).map(function (k) {
            return '<option value="' + esc(k) + '"' + (k === selected ? ' selected' : '') + '>' + esc(CFG.floorAccess[k]) + '</option>';
        }).join('');
    }

    function addFloor(data) {
        data = data || {};
        var i = nextIndex++;
        var row = document.createElement('div');
        row.className = 'floor-row';
        row.innerHTML =
            '<div><label for="floor-level-' + i + '">Level</label>' +
            '<input type="number" id="floor-level-' + i + '" name="floors[' + i + '][level]" min="-5" max="60" step="1" value="' + esc(data.level === undefined ? '' : data.level) + '" required></div>' +
            '<div><label for="floor-label-' + i + '">Name</label>' +
            '<input type="text" id="floor-label-' + i + '" name="floors[' + i + '][label]" maxlength="60" value="' + esc(data.label || '') + '" placeholder="Ground floor"></div>' +
            '<div><label for="floor-access-' + i + '">How people reach it</label>' +
            '<select id="floor-access-' + i + '" name="floors[' + i + '][access]">' + accessOptions(data.access || 'ground') + '</select></div>' +
            '<div class="floor-services"><label for="floor-services-' + i + '">What is on this floor (optional)</label>' +
            '<input type="text" id="floor-services-' + i + '" name="floors[' + i + '][services]" maxlength="255" value="' + esc(data.services || '') + '" placeholder="Offices, clinic, library"></div>' +
            '<button type="button" class="btn btn-danger floor-remove" aria-label="Remove this floor">Remove</button>';
        row.querySelector('.floor-remove').addEventListener('click', function () { row.remove(); });
        rowsBox.appendChild(row);
    }

    function setFloors(list) {
        rowsBox.innerHTML = '';
        nextIndex = 0;
        (list || []).forEach(addFloor);
    }

    (CFG.floors || []).forEach(addFloor);
    document.getElementById('add-floor').addEventListener('click', function () {
        var levels = $all('input[name$="[level]"]', rowsBox).map(function (el) { return Number(el.value); }).filter(function (n) { return !isNaN(n); });
        var next = levels.length ? Math.max.apply(null, levels) + 1 : 0;
        addFloor({ level: next, label: next === 0 ? 'Ground floor' : 'Floor ' + (next + 1), access: next === 0 ? 'ground' : 'elevator' });
    });

    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    // ---- presets ------------------------------------------------------------------------
    var presetRow = document.getElementById('preset-row');
    if (presetRow) {
        var status = document.createElement('p');
        status.className = 'meta'; status.setAttribute('aria-live', 'polite');
        presetRow.parentNode.appendChild(status);

        presetRow.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-preset]');
            if (!btn) return;
            var preset = CFG.presets[btn.dataset.preset];
            if (!preset) return;

            if (preset.category) form.querySelector('#category').value = preset.category;
            CFG.featureKeys.forEach(function (key) {
                var box = form.querySelector('input[name="' + key + '"]');
                if (box) box.checked = (preset.features || []).indexOf(key) !== -1;
            });
            $all('input[name="amenities[]"]', form).forEach(function (box) {
                box.checked = (preset.amenities || []).indexOf(box.value) !== -1;
            });
            var hours = form.querySelector('#hours');
            if (preset.hours && hours && !hours.value) hours.value = preset.hours;
            if (preset.floors) setFloors(preset.floors);

            $all('.chip', presetRow).forEach(function (c) { c.setAttribute('aria-pressed', c === btn ? 'true' : 'false'); });
            status.textContent = 'Applied "' + preset.label + '". Check each item against what you actually see, then adjust.';
        });
    }

    // ---- map pin picker -----------------------------------------------------------------------
    var latInput = document.getElementById('latitude');
    var lngInput = document.getElementById('longitude');
    var statusText = document.getElementById('location-status');
    var coordText = document.getElementById('coordinate-readout');

    var picker = L.map('pick-map').setView(CFG.center, 14);
    var base = AA.BASEMAPS.classic; // classic OpenStreetMap tiles, to match the explore map's default
    L.tileLayer(base.url, base.options).addTo(picker);
    var pin = null;

    function setPoint(lat, lng, message) {
        lat = Number(lat); lng = Number(lng);
        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);
        if (pin) { pin.setLatLng([lat, lng]); }
        else {
            pin = L.marker([lat, lng], { draggable: true, title: 'Facility location' }).addTo(picker);
            pin.on('dragend', function (ev) { var p = ev.target.getLatLng(); setPoint(p.lat, p.lng); });
        }
        coordText.textContent = 'Coordinates: ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
        statusText.textContent = message || 'Location selected. Click elsewhere or drag the pin to adjust it.';
    }

    picker.on('click', function (e) { setPoint(e.latlng.lat, e.latlng.lng); });

    if (latInput.value && lngInput.value) {
        picker.setView([Number(latInput.value), Number(lngInput.value)], 17);
        setPoint(latInput.value, lngInput.value);
    }

    document.getElementById('use-location').addEventListener('click', function () {
        if (!navigator.geolocation) { statusText.textContent = 'Location is not supported by this browser. Search or click the map instead.'; return; }
        statusText.textContent = 'Getting your location…';
        navigator.geolocation.getCurrentPosition(function (pos) {
            picker.setView([pos.coords.latitude, pos.coords.longitude], 18);
            setPoint(pos.coords.latitude, pos.coords.longitude, 'Your current location was selected. Check that the pin is on the facility.');
        }, function () {
            statusText.textContent = 'Could not get your location. Search for the place or click the map instead.';
        }, { enableHighAccuracy: true, timeout: 10000 });
    });

    // Place search uses OpenStreetMap's Nominatim service (light use only, one request per click).
    function searchPlace() {
        var q = document.getElementById('place-search').value.trim();
        if (!q) { statusText.textContent = 'Type a place or street to search.'; return; }
        statusText.textContent = 'Searching…';
        fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=ph&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
            .then(function (list) {
                if (!list.length) { statusText.textContent = 'No match found. Try a nearby street or barangay, or click the map.'; return; }
                var hit = list[0];
                picker.setView([Number(hit.lat), Number(hit.lon)], 18);
                setPoint(hit.lat, hit.lon, 'Found "' + hit.display_name + '". Move the pin onto the exact entrance if needed.');
            })
            .catch(function () { statusText.textContent = 'Search is not available right now. Click the map to place the pin.'; });
    }
    document.getElementById('place-search-btn').addEventListener('click', searchPlace);
    document.getElementById('place-search').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); searchPlace(); }
    });

    form.addEventListener('submit', function (e) {
        if (!latInput.value || !lngInput.value) {
            e.preventDefault();
            statusText.textContent = 'Please select the facility location on the map before submitting.';
            document.getElementById('pick-map').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    setTimeout(function () { picker.invalidateSize(); }, 150);
}());
