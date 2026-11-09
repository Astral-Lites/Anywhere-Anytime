/*
 * Anywhere, Anytime: shared helpers (no DOM, no map).
 * Distance and direction maths, accessibility matching, route parsing, and slope statistics.
 * Kept separate from the map code so it can be tested on its own (see tests/core.test.js).
 */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) { module.exports = factory(); }
    else { root.AA = factory(); }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var EARTH_RADIUS_M = 6371008.8;
    var toRad = function (d) { return d * Math.PI / 180; };
    var toDeg = function (r) { return r * 180 / Math.PI; };

    /** Straight-line distance in metres between two {lat, lng} points. */
    function haversine(a, b) {
        var dLat = toRad(b.lat - a.lat);
        var dLng = toRad(b.lng - a.lng);
        var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return 2 * EARTH_RADIUS_M * Math.asin(Math.min(1, Math.sqrt(h)));
    }

    /** Initial compass bearing (0 to 360, 0 = north) from a to b. */
    function bearing(a, b) {
        var y = Math.sin(toRad(b.lng - a.lng)) * Math.cos(toRad(b.lat));
        var x = Math.cos(toRad(a.lat)) * Math.sin(toRad(b.lat)) -
            Math.sin(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.cos(toRad(b.lng - a.lng));
        return (toDeg(Math.atan2(y, x)) + 360) % 360;
    }

    var COMPASS = ['north', 'northeast', 'east', 'southeast', 'south', 'southwest', 'west', 'northwest'];
    function compassName(deg) { return COMPASS[Math.round(((deg % 360) + 360) % 360 / 45) % 8]; }

    function formatDistance(m) {
        if (m == null || isNaN(m)) return '';
        if (m < 950) return Math.round(m / 10) * 10 + ' m';
        return (m / 1000).toFixed(m < 10000 ? 1 : 0) + ' km';
    }

    function formatDuration(s) {
        if (s == null || isNaN(s)) return '';
        var min = Math.max(1, Math.round(s / 60));
        if (min < 60) return min + ' min';
        var h = Math.floor(min / 60), r = min % 60;
        return h + ' h' + (r ? ' ' + r + ' min' : '');
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
        });
    }

    /**
     * How well a facility fits an accessibility profile.
     * need = { features: [...], amenities: [...] } (see Facility::NEEDS).
     * Returns { pct, have, total, missing: [feature or amenity keys] }.
     */
    function matchScore(facility, need) {
        if (!need) return { pct: facility.score, have: 0, total: 0, missing: [] };
        var missing = [], have = 0;
        (need.features || []).forEach(function (key) {
            if (facility.features && facility.features[key]) have++; else missing.push(key);
        });
        (need.amenities || []).forEach(function (key) {
            if ((facility.amenities || []).indexOf(key) !== -1) have++; else missing.push(key);
        });
        var total = (need.features || []).length + (need.amenities || []).length;
        return { pct: total ? Math.round(have / total * 100) : 0, have: have, total: total, missing: missing };
    }

    /** good, fair, or low, from a 0 to 100 rating. Shown with text as well as color. */
    function band(pct) { return pct >= 70 ? 'good' : (pct >= 40 ? 'fair' : 'low'); }

    // ---- Routing -----------------------------------------------------------------------

    // The public OSM-based routing servers run by FOSSGIS. Walking and cycling use their own profiles.
    var ROUTE_MODES = {
        walk: { label: 'Walk', server: 'routed-foot', speedKmh: null, icon: '🚶' },
        wheelchair: { label: 'Wheelchair pace', server: 'routed-foot', speedKmh: 3.0, icon: '♿' },
        cycle: { label: 'Cycle', server: 'routed-bike', speedKmh: null, icon: '🚲' },
        drive: { label: 'Drive', server: 'routed-car', speedKmh: null, icon: '🚗' }
    };

    function routeUrl(modeKey, from, to) {
        var mode = ROUTE_MODES[modeKey] || ROUTE_MODES.walk;
        return 'https://routing.openstreetmap.de/' + mode.server + '/route/v1/driving/' +
            from.lng.toFixed(6) + ',' + from.lat.toFixed(6) + ';' + to.lng.toFixed(6) + ',' + to.lat.toFixed(6) +
            '?overview=full&geometries=geojson&steps=true';
    }

    var ARROWS = {
        'uturn': '↶', 'sharp right': '↘', 'right': '→', 'slight right': '↗', 'straight': '↑',
        'slight left': '↖', 'left': '←', 'sharp left': '↙'
    };

    /** One routing step into { icon, text, distance, location }. */
    function describeStep(step) {
        var m = step.maneuver || {};
        var type = m.type || 'continue';
        var mod = m.modifier || '';
        var road = step.name ? ' onto ' + step.name : '';
        var icon = ARROWS[mod] || '↑';
        var text;

        if (type === 'depart') {
            icon = '▶';
            text = 'Head ' + compassName(m.bearing_after || 0) + (step.name ? ' on ' + step.name : '');
        } else if (type === 'arrive') {
            icon = '⚑';
            text = 'You have arrived' + (mod === 'left' ? '. The destination is on your left' : (mod === 'right' ? '. The destination is on your right' : ''));
        } else if (type === 'roundabout' || type === 'rotary' || type === 'roundabout turn') {
            icon = '⟳';
            text = 'At the roundabout, take exit ' + (m.exit || 1) + road;
        } else if (type === 'turn' || type === 'end of road' || type === 'fork' || type === 'on ramp' || type === 'off ramp') {
            var phrase = mod === 'straight' ? 'Continue straight' : (mod === 'uturn' ? 'Make a U-turn' : 'Turn ' + (mod || 'ahead'));
            text = phrase + road;
        } else {
            text = (mod && mod !== 'straight' ? 'Bear ' + mod : 'Continue') + road;
        }

        return {
            icon: icon,
            text: text,
            distance: step.distance || 0,
            location: m.location ? { lat: m.location[1], lng: m.location[0] } : null
        };
    }

    /** Parse an OSRM JSON response into our own simple route object, or null if it has no route. */
    function parseRoute(json, modeKey) {
        if (!json || json.code !== 'Ok' || !json.routes || !json.routes.length) return null;
        var r = json.routes[0];
        var mode = ROUTE_MODES[modeKey] || ROUTE_MODES.walk;
        var coords = (r.geometry && r.geometry.coordinates || []).map(function (c) { return { lat: c[1], lng: c[0] }; });
        var steps = [];
        (r.legs || []).forEach(function (leg) { (leg.steps || []).forEach(function (s) { steps.push(describeStep(s)); }); });
        // "Wheelchair pace" uses the walking route with a slower speed for the time estimate.
        var duration = mode.speedKmh ? r.distance / (mode.speedKmh * 1000 / 3600) : r.duration;
        return { distance: r.distance, duration: duration, coords: coords, steps: steps, mode: modeKey };
    }

    /** Fallback when the routing service is unreachable: a straight line with distance and heading. */
    function straightLineRoute(from, to, modeKey) {
        var mode = ROUTE_MODES[modeKey] || ROUTE_MODES.walk;
        var d = haversine(from, to);
        var speed = (mode.speedKmh || (modeKey === 'drive' ? 30 : (modeKey === 'cycle' ? 14 : 4.5))) * 1000 / 3600;
        var heading = compassName(bearing(from, to));
        return {
            distance: d, duration: d / speed, coords: [from, to], mode: modeKey, straight: true,
            steps: [
                { icon: '▶', text: 'Head ' + heading + ' in a straight line (no street data available)', distance: d, location: from },
                { icon: '⚑', text: 'You have arrived', distance: 0, location: to }
            ]
        };
    }

    /**
     * Progress along the route. `idx` is the step the user is on; the next maneuver to show is steps[idx + 1].
     * When the user comes within `radius` metres of an upcoming maneuver point, they have reached it and idx moves on.
     * The index never goes backwards, so GPS jitter cannot undo progress.
     */
    function advanceStep(pos, steps, idx, radius) {
        radius = radius || 20;
        var result = idx;
        for (var j = idx + 1; j < Math.min(steps.length, idx + 4); j++) {
            if (steps[j].location && haversine(pos, steps[j].location) <= radius) result = j;
        }
        return result;
    }

    /** Distance in metres from a point to the closest vertex of the route line (good enough for off-route checks). */
    function distanceToRoute(pos, coords) {
        var best = Infinity;
        for (var i = 0; i < coords.length - 1; i++) {
            var d = pointToSegment(pos, coords[i], coords[i + 1]);
            if (d < best) best = d;
        }
        if (coords.length === 1) best = haversine(pos, coords[0]);
        return best;
    }

    function pointToSegment(p, a, b) {
        // Equirectangular approximation: accurate enough over a few hundred metres.
        var k = Math.cos(toRad(p.lat));
        var ax = (a.lng - p.lng) * k, ay = a.lat - p.lat;
        var bx = (b.lng - p.lng) * k, by = b.lat - p.lat;
        var dx = bx - ax, dy = by - ay;
        var len2 = dx * dx + dy * dy;
        var t = len2 ? Math.max(0, Math.min(1, -(ax * dx + ay * dy) / len2)) : 0;
        var cx = ax + t * dx, cy = ay + t * dy;
        return Math.sqrt(cx * cx + cy * cy) * toRad(1) * EARTH_RADIUS_M;
    }

    // ---- Elevation and slope -----------------------------------------------------------

    /** Pick about `max` points along the route, evenly spaced by distance. Returns [{lat, lng, d}] with d in metres. */
    function sampleRoute(coords, max) {
        if (!coords.length) return [];
        var cumulative = [0];
        for (var i = 1; i < coords.length; i++) cumulative.push(cumulative[i - 1] + haversine(coords[i - 1], coords[i]));
        var total = cumulative[cumulative.length - 1];
        if (total === 0) return [{ lat: coords[0].lat, lng: coords[0].lng, d: 0 }];
        var n = Math.max(2, Math.min(max, Math.ceil(total / 40) + 1));
        var out = [], j = 0;
        for (var s = 0; s < n; s++) {
            var target = total * s / (n - 1);
            while (j < coords.length - 2 && cumulative[j + 1] < target) j++;
            var segLen = cumulative[j + 1] - cumulative[j];
            var t = segLen ? (target - cumulative[j]) / segLen : 0;
            out.push({
                lat: coords[j].lat + (coords[j + 1].lat - coords[j].lat) * t,
                lng: coords[j].lng + (coords[j + 1].lng - coords[j].lng) * t,
                d: target
            });
        }
        return out;
    }

    /**
     * Ascent, descent and steepest grade from [{d, elev}].
     * Grade is measured over windows of at least `minWindow` metres, because the free elevation
     * data is coarse (about 90 m cells) and shorter windows would only measure noise.
     */
    function slopeStats(profile, minWindow) {
        minWindow = minWindow || 100;
        var ascent = 0, descent = 0, steepest = 0;
        for (var i = 1; i < profile.length; i++) {
            var diff = profile[i].elev - profile[i - 1].elev;
            if (diff > 0) ascent += diff; else descent -= diff;
        }
        for (var a = 0; a < profile.length; a++) {
            for (var b = a + 1; b < profile.length; b++) {
                var run = profile[b].d - profile[a].d;
                if (run >= minWindow) {
                    var g = Math.abs(profile[b].elev - profile[a].elev) / run * 100;
                    if (g > steepest) steepest = g;
                    break;
                }
            }
        }
        var label = steepest < 5 ? 'Mostly flat' : (steepest < 8.3 ? 'Some gentle slopes' : 'Steep section, may be hard for wheelchair users');
        return { ascent: ascent, descent: descent, steepest: steepest, label: label };
    }

    // ---- Basemaps ----------------------------------------------------------------------

    // "classic" is the standard OpenStreetMap tile style used by the thesis (Leaflet + OpenStreetMap)
    // and is the default everywhere. The others are optional alternative styles, offered as an extra
    // feature; "explorer" and "night" are still rendered from OpenStreetMap's own map data (via CARTO).
    var BASEMAPS = {
        classic: {
            label: 'Classic OSM (default)',
            url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            options: { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' }
        },
        explorer: {
            label: 'Explorer (OSM data, alternate style)',
            url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
            options: { subdomains: 'abcd', maxZoom: 20, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>' }
        },
        night: {
            label: 'Night (OSM data, alternate style)',
            url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
            options: { subdomains: 'abcd', maxZoom: 20, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>' }
        },
        satellite: {
            label: 'Satellite (not OpenStreetMap)',
            url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            options: { maxZoom: 19, attribution: 'Tiles &copy; Esri, Maxar, Earthstar Geographics' }
        }
    };

    return {
        haversine: haversine, bearing: bearing, compassName: compassName,
        formatDistance: formatDistance, formatDuration: formatDuration, escapeHtml: escapeHtml,
        matchScore: matchScore, band: band,
        ROUTE_MODES: ROUTE_MODES, routeUrl: routeUrl, describeStep: describeStep, parseRoute: parseRoute,
        straightLineRoute: straightLineRoute, advanceStep: advanceStep, distanceToRoute: distanceToRoute,
        sampleRoute: sampleRoute, slopeStats: slopeStats, BASEMAPS: BASEMAPS
    };
}));
