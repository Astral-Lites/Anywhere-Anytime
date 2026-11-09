/*
 * Anywhere, Anytime: the explore map.
 * Leaflet + OpenStreetMap-based tiles, with a game-style HUD: category pins, "you are here" marker,
 * radar rings, accessibility-profile matching, turn-by-turn directions, floor picker, and route slope.
 * Pure calculations live in anywhere-core.js (window.AA).
 */
(function () {
    'use strict';

    // Fail loudly instead of leaving a blank map: if a CDN script was blocked or failed to
    // load (ad blocker, offline, slow network), show why instead of silently doing nothing.
    function fatal(message, err) {
        if (err) console.error('Anywhere, Anytime map:', message, err); else console.error('Anywhere, Anytime map:', message);
        var el = document.getElementById('facility-map');
        if (el) {
            el.innerHTML = '<div style="max-width:420px;margin:3rem auto;padding:1.2rem 1.4rem;background:#fff;' +
                'border:1px solid #d3e0de;border-radius:10px;font:1rem/1.5 system-ui,sans-serif;color:#1d2b33">' +
                '<strong>The map could not start.</strong><p style="margin:.6rem 0 0;color:#51636d">' + message + '</p>' +
                '<p style="margin:.6rem 0 0;color:#51636d">Check your internet connection and reload the page. ' +
                'If this keeps happening, open the browser console (F12) for the technical error.</p></div>';
        }
    }

    if (typeof L === 'undefined') { fatal('The Leaflet map library did not load. It is loaded from a CDN in layouts/app.blade.php, so this usually means no internet connection or that address is blocked.'); return; }
    if (!window.AA) { fatal('anywhere-core.js did not load before anywhere-map.js. Check the script order in directory/index.blade.php.'); return; }
    if (!window.AA_DATA) { fatal('The facility data was not found on the page (window.AA_DATA). Check that directory/index.blade.php sets it before anywhere-map.js runs.'); return; }
    if (!document.getElementById('facility-map')) { return; }

    var AA = window.AA;
    var DATA = window.AA_DATA;
    var CFG = DATA.config;
    var esc = AA.escapeHtml;

    var CATEGORY_STYLE = {
        Health: ['🏥', '#c93c3c'], Education: ['🎓', '#2f62c9'], Government: ['🏛️', '#6f45bd'],
        Commercial: ['🛒', '#b87400'], Religious: ['⛪', '#0b7d6e'], Recreation: ['⚽', '#2a8f48'],
        Transportation: ['🚌', '#465866'], Other: ['📍', '#666666']
    };
    var ACCESS_ICON = { ground: '🚪', elevator: '🛗', lift: '⏫', ramp: '♿', stairs: '🪜' };

    // ---- small helpers ------------------------------------------------------------------
    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function store(key, value) {
        try {
            if (value === undefined) return localStorage.getItem('aa.' + key);
            localStorage.setItem('aa.' + key, value);
        } catch (e) { return null; }
        return null;
    }
    var isNarrow = function () { return window.matchMedia('(max-width: 800px)').matches; };

    // ---- state --------------------------------------------------------------------------
    var state = {
        points: DATA.points,
        need: store('need') || '',
        basemap: 'classic', // always start on classic OpenStreetMap tiles, the one style guaranteed to work
        q: '', category: '', features: {}, minMatch: false, radiusKm: 0, sort: 'auto',
        selectedId: null, floorLevel: null,
        player: null, accuracy: null, watchId: null, follow: false, manualMode: false, manualPlayer: false,
        route: null, routeMode: 'walk', stepIdx: 0, voice: false, routeFor: null, lastReroute: 0, routeToken: 0
    };
    if (!CFG.needs[state.need]) state.need = '';
    var byId = {};
    state.points.forEach(function (p) { byId[p.id] = p; });

    // ---- map ----------------------------------------------------------------------------
    var map = L.map('facility-map', { zoomControl: false, zoomSnap: 0.5, minZoom: 3, maxZoom: 19 }).setView(CFG.center, 14);
    L.control.zoom({ position: 'bottomright' }).addTo(map);
    L.control.scale({ position: 'bottomleft', imperial: false }).addTo(map);

    var baseLayer = null;
    var tileFailCount = 0, tileOkCount = 0, tileWarned = false;
    function setBasemap(key) {
        if (!AA.BASEMAPS[key]) key = 'classic';
        if (baseLayer) map.removeLayer(baseLayer);
        var def = AA.BASEMAPS[key];
        tileFailCount = 0; tileOkCount = 0; tileWarned = false;
        baseLayer = L.tileLayer(def.url, def.options).addTo(map);
        // If map tiles cannot be fetched (offline, or the tile server is blocked on this network),
        // say so instead of leaving a plain grey box with no explanation.
        baseLayer.on('tileerror', function () {
            tileFailCount++;
            if (!tileWarned && tileFailCount >= 4 && tileOkCount === 0) {
                tileWarned = true;
                if (key !== 'classic') {
                    toast('"' + def.label + '" map images could not be loaded on this network. Switched back to classic OpenStreetMap.');
                    setBasemap('classic');
                } else {
                    toast('Map images could not be loaded. Check your internet connection.');
                }
            }
        });
        baseLayer.on('tileload', function () { tileOkCount++; });
        state.basemap = key;
        store('basemap', key);
        document.getElementById('aa-app').setAttribute('data-theme', key === 'night' ? 'night' : 'day');
        $all('input[name="basemap"]').forEach(function (r) { r.checked = r.value === key; });
    }

    // Plain layer group, not a clustering plugin: one less moving part, and it matches
    // the simple, reliable approach already used by the location-picker map in the form.
    var cluster = L.layerGroup().addTo(map);
    var routeLayer = L.layerGroup().addTo(map);
    var playerLayer = L.layerGroup().addTo(map);

    // ---- matching and filtering -----------------------------------------------------------
    function needSpec() { return state.need ? CFG.needs[state.need] : null; }
    function matchOf(p) { return AA.matchScore(p, needSpec()); }
    function ratingOf(p) { return matchOf(p).pct; }
    function distOf(p) { return state.player ? AA.haversine(state.player, p) : null; }

    function passes(p) {
        if (state.q) {
            var hay = (p.name + ' ' + p.address + ' ' + p.category).toLowerCase();
            if (hay.indexOf(state.q.toLowerCase()) === -1) return false;
        }
        if (state.category && p.category !== state.category) return false;
        for (var key in state.features) { if (state.features[key] && !p.features[key]) return false; }
        if (state.minMatch && ratingOf(p) < 70) return false;
        if (state.radiusKm > 0 && state.player && distOf(p) > state.radiusKm * 1000) return false;
        return true;
    }

    function sortedVisible() {
        var list = state.points.filter(passes);
        var mode = state.sort;
        if (mode === 'auto') mode = state.player ? 'near' : (state.need ? 'match' : 'name');
        list.sort(function (a, b) {
            if (mode === 'near' && state.player) return distOf(a) - distOf(b);
            if (mode === 'match') return ratingOf(b) - ratingOf(a) || a.name.localeCompare(b.name);
            if (mode === 'score') return b.score - a.score || a.name.localeCompare(b.name);
            return a.name.localeCompare(b.name);
        });
        return list;
    }

    // ---- markers ------------------------------------------------------------------------
    function pinIcon(p) {
        var style = CATEGORY_STYLE[p.category] || CATEGORY_STYLE.Other;
        var rating = ratingOf(p);
        var cls = 'pin pin-' + AA.band(rating) + (p.id === state.selectedId ? ' is-selected' : '');
        return L.divIcon({
            className: 'pin-wrap',
            html: '<div class="' + cls + '" style="--cat:' + style[1] + '"><span class="pin-ico" aria-hidden="true">' + style[0] + '</span><span class="pin-num">' + rating + '</span></div>',
            iconSize: [46, 54], iconAnchor: [23, 52], popupAnchor: [0, -50]
        });
    }

    state.points.forEach(function (p) {
        p._marker = L.marker([p.lat, p.lng], { icon: pinIcon(p), title: p.name, alt: p.name + ', ' + p.category, keyboard: true });
        p._marker.on('click', function () { select(p.id, { fly: false }); });
    });

    function refreshMarkers() {
        var visible = state.points.filter(passes);
        cluster.clearLayers();
        visible.forEach(function (p) { p._marker.setIcon(pinIcon(p)); cluster.addLayer(p._marker); });
        return visible;
    }

    // ---- sidebar: filters + list ----------------------------------------------------------
    function buildFilters() {
        var cat = $('#f-category');
        CFG.categories.forEach(function (c) {
            var o = document.createElement('option'); o.value = c; o.textContent = c; cat.appendChild(o);
        });
        var needSel = $('#f-need');
        Object.keys(CFG.needs).forEach(function (k) {
            var o = document.createElement('option'); o.value = k; o.textContent = CFG.needs[k].label; needSel.appendChild(o);
        });
        needSel.value = state.need;

        var wrap = $('#f-features');
        Object.keys(CFG.features).forEach(function (key) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'chip'; b.setAttribute('aria-pressed', 'false'); b.dataset.feature = key;
            b.innerHTML = '<span aria-hidden="true">' + esc(CFG.featureIcons[key] || '') + '</span> ' + esc(CFG.features[key]);
            b.addEventListener('click', function () {
                state.features[key] = !state.features[key];
                b.setAttribute('aria-pressed', state.features[key] ? 'true' : 'false');
                refreshAll();
            });
            wrap.appendChild(b);
        });
    }

    function renderList(visible) {
        var ol = $('#aa-list');
        ol.innerHTML = '';
        $('#aa-count').textContent = visible.length + (visible.length === 1 ? ' facility' : ' facilities');
        if (!visible.length) {
            ol.innerHTML = '<li class="aa-empty">No facilities match. Try removing a filter.</li>';
            return;
        }
        var labelWord = state.need ? 'match' : 'access';
        visible.forEach(function (p) {
            var rating = ratingOf(p), d = distOf(p);
            var style = CATEGORY_STYLE[p.category] || CATEGORY_STYLE.Other;
            var li = document.createElement('li');
            li.innerHTML = '<button type="button" class="aa-item' + (p.id === state.selectedId ? ' active' : '') + '" data-id="' + p.id + '">' +
                '<span class="aa-item-ico" style="background:' + style[1] + '" aria-hidden="true">' + style[0] + '</span>' +
                '<span class="aa-item-body"><strong>' + esc(p.name) + '</strong>' +
                '<span class="aa-item-meta">' + esc(p.category) + (d != null ? ' · ' + AA.formatDistance(d) + ' away' : '') + '</span></span>' +
                '<span class="score score-' + AA.band(rating) + '"><b>' + rating + '%</b><small>' + labelWord + '</small></span></button>';
            ol.appendChild(li);
        });
    }

    function refreshAll() {
        var visible = refreshMarkers();
        renderList(visible);
        updateHud();
        // keep the open details in sync with the profile selected
        if (state.selectedId && byId[state.selectedId]) renderDetail(byId[state.selectedId], true);
    }

    function updateHud() {
        var hud = $('#aa-hud');
        var inView = state.points.filter(function (p) { return passes(p) && map.getBounds().contains([p.lat, p.lng]); }).length;
        var text = '📡 ' + inView + ' in view';
        if (state.player) {
            var nearest = null;
            state.points.filter(passes).forEach(function (p) {
                var d = distOf(p);
                if (nearest === null || d < nearest.d) nearest = { p: p, d: d };
            });
            if (nearest) text += ' · Nearest: ' + nearest.p.name + ' (' + AA.formatDistance(nearest.d) + ')';
        }
        hud.textContent = text;
    }

    // ---- selecting a facility + details panel -------------------------------------------------
    function select(id, opts) {
        opts = opts || {};
        var p = byId[id];
        if (!p) return;
        var previous = state.selectedId;
        state.selectedId = id;
        state.floorLevel = null;
        if (previous && byId[previous]) byId[previous]._marker.setIcon(pinIcon(byId[previous]));
        p._marker.setIcon(pinIcon(p));
        if (state.routeFor !== null && state.routeFor !== id) clearRoute();
        renderDetail(p);
        $('#aa-detail').hidden = false;
        $('#aa-app').classList.add('detail-open');
        if (isNarrow()) $('#aa-side').classList.remove('open');
        renderList(sortedVisible());
        if (opts.fly !== false) {
            var zoom = Math.max(map.getZoom(), 17);
            var target = L.latLng(p.lat, p.lng);
            if (!isNarrow()) { // keep the pin clear of the details panel
                var pt = map.project(target, zoom).add([190, 0]);
                target = map.unproject(pt, zoom);
            }
            map.flyTo(target, zoom, { duration: 0.9 });
        }
        if (opts.focus) { var h = $('#aa-detail h2'); if (h) h.focus(); }
    }

    function closeDetail() {
        var previous = state.selectedId;
        state.selectedId = null;
        if (previous && byId[previous]) byId[previous]._marker.setIcon(pinIcon(byId[previous]));
        $('#aa-detail').hidden = true;
        $('#aa-app').classList.remove('detail-open');
        renderList(sortedVisible());
    }

    function ringSvg(pct) {
        var r = 26, c = 2 * Math.PI * r, dash = c * pct / 100;
        return '<svg class="ring ring-' + AA.band(pct) + '" viewBox="0 0 64 64" role="img" aria-label="' + pct + ' percent">' +
            '<circle cx="32" cy="32" r="' + r + '" class="ring-bg"/>' +
            '<circle cx="32" cy="32" r="' + r + '" class="ring-fg" stroke-dasharray="' + dash.toFixed(1) + ' ' + c.toFixed(1) + '" transform="rotate(-90 32 32)"/>' +
            '<text x="32" y="37" text-anchor="middle" class="ring-text">' + pct + '</text></svg>';
    }

    function renderDetail(p, keepScroll) {
        var panel = $('#aa-detail');
        var scroll = keepScroll ? panel.scrollTop : 0;
        var m = matchOf(p);
        var html = '';
        html += '<div class="d-head"><button type="button" class="icon-btn" id="d-close" aria-label="Close details">✕</button>' +
            '<span class="category-pill">' + esc(p.category) + '</span></div>';
        html += '<h2 tabindex="-1">' + esc(p.name) + '</h2><p class="d-addr">' + esc(p.address) + '</p>';

        html += '<div class="d-score">' + ringSvg(p.score) +
            '<div><strong>' + esc(p.level) + ' accessibility</strong><span>' + countFeatures(p) + ' of ' + Object.keys(CFG.features).length + ' features available</span></div></div>';

        if (state.need) {
            var missing = m.missing.map(function (k) { return CFG.features[k] || CFG.amenities[k] || k; });
            html += '<div class="d-match d-match-' + AA.band(m.pct) + '"><strong>' + m.pct + '% match for: ' + esc(CFG.needs[state.need].label) + '</strong>' +
                '<div class="bar"><span style="width:' + m.pct + '%"></span></div>' +
                (missing.length ? '<span class="d-missing">Missing: ' + esc(missing.join(', ')) + '</span>' : '<span class="d-missing">Has everything this profile looks for.</span>') + '</div>';
        } else {
            html += '<p class="d-hint">Pick "I need access for" in the list to see how well this place fits you.</p>';
        }

        html += '<div class="d-actions"><button type="button" class="btn" id="d-directions">🧭 Get directions</button>';
        if (p.updateUrl) html += '<a class="btn btn-light" href="' + esc(p.updateUrl) + '">Request an update</a>';
        html += '</div><div id="aa-directions" class="d-directions" aria-live="polite"></div>';

        html += '<h3>Accessibility features</h3><ul class="feat-list">';
        Object.keys(CFG.features).forEach(function (k) {
            var has = p.features[k];
            html += '<li class="' + (has ? 'yes' : 'no') + '"><span aria-hidden="true">' + (has ? '✔' : '✖') + '</span> ' +
                '<span aria-hidden="true">' + esc(CFG.featureIcons[k] || '') + '</span> ' + esc(CFG.features[k]) + '<span class="sr-only">: ' + (has ? 'available' : 'not available') + '</span></li>';
        });
        html += '</ul>';

        if (p.amenities.length) {
            html += '<h3>Services</h3><p>' + p.amenities.map(function (k) { return '<span class="chip chip-static">' + esc(CFG.amenities[k] || k) + '</span>'; }).join('') + '</p>';
        }

        if (p.surface || p.slope) {
            html += '<h3>Getting there</h3><ul class="plain">' +
                (p.surface ? '<li>Surface: ' + esc(p.surface) + '</li>' : '') +
                (p.slope ? '<li>Approach: ' + esc(p.slope) + (p.slope === 'Steep slope' ? ' ⚠' : '') + '</li>' : '') + '</ul>';
        }

        if (p.floors.length) html += floorsHtml(p);

        if (p.hours || p.contact) {
            html += '<h3>Visit</h3><ul class="plain">' + (p.hours ? '<li>🕒 ' + esc(p.hours) + '</li>' : '') + (p.contact ? '<li>☎ ' + esc(p.contact) + '</li>' : '') + '</ul>';
        }
        if (p.notes) html += '<h3>Notes</h3><p>' + esc(p.notes) + '</p>';
        if (p.verified) html += '<p class="d-verified">Reviewed by an admin on ' + esc(p.verified) + '.</p>';

        panel.innerHTML = html;
        panel.scrollTop = scroll;
        $('#d-close').addEventListener('click', closeDetail);
        $('#d-directions').addEventListener('click', function () { startRoute(p, state.routeMode); });
        $all('.floor-btn', panel).forEach(function (b) {
            b.addEventListener('click', function () { state.floorLevel = Number(b.dataset.level); renderFloorInfo(p); });
        });
        if (p.floors.length) renderFloorInfo(p);
        if (state.routeFor === p.id && state.route) renderDirections(p);
    }

    function countFeatures(p) { return Object.keys(p.features).filter(function (k) { return p.features[k]; }).length; }

    // Floor picker: a "building stack" like the level picker on indoor maps.
    function floorsHtml(p) {
        var floors = p.floors.slice().sort(function (a, b) { return b.level - a.level; });
        var stepFree = floors.every(function (f) { return f.access !== 'stairs'; });
        var html = '<h3>Floors</h3><p class="' + (stepFree ? 'floor-ok' : 'floor-warn') + '">' +
            (stepFree ? '✔ Every listed floor can be reached without stairs.' : '⚠ At least one floor can only be reached by stairs.') + '</p>';
        html += '<div class="floors"><div class="floor-stack" role="group" aria-label="Choose a floor">';
        floors.forEach(function (f) {
            html += '<button type="button" class="floor-btn" data-level="' + f.level + '" aria-pressed="false">' +
                '<b>' + (f.level > 0 ? '+' + f.level : f.level) + '</b><span>' + esc(f.label) + '</span><i aria-hidden="true">' + (ACCESS_ICON[f.access] || '') + '</i></button>';
        });
        html += '</div><div class="floor-info" id="floor-info" aria-live="polite"></div></div>';
        return html;
    }

    function renderFloorInfo(p) {
        var floors = p.floors;
        var level = state.floorLevel;
        if (level === null) {
            var ground = floors.filter(function (f) { return f.level === 0; })[0];
            level = (ground || floors[0]).level;
            state.floorLevel = level;
        }
        var f = floors.filter(function (x) { return x.level === level; })[0];
        $all('.floor-btn').forEach(function (b) { b.setAttribute('aria-pressed', Number(b.dataset.level) === level ? 'true' : 'false'); });
        if (!f) return;
        var stairs = f.access === 'stairs';
        $('#floor-info').innerHTML = '<strong>' + esc(f.label) + '</strong>' +
            '<span class="' + (stairs ? 'floor-warn' : 'floor-ok') + '">' + (ACCESS_ICON[f.access] || '') + ' ' + esc(CFG.floorAccess[f.access] || f.access) + '</span>' +
            (f.services ? '<span>' + esc(f.services) + '</span>' : '<span class="meta">No services listed for this floor.</span>');
    }

    // ---- "you are here" -----------------------------------------------------------------------
    var playerMarker = null, accuracyCircle = null, radarRings = [];

    function setPlayer(lat, lng, opts) {
        opts = opts || {};
        state.player = { lat: lat, lng: lng };
        state.accuracy = opts.accuracy || null;
        var ll = [lat, lng];
        if (!playerMarker) {
            playerMarker = L.marker(ll, {
                icon: L.divIcon({ className: 'player-wrap', html: '<div class="player"><i class="player-cone"></i><i class="player-pulse"></i><i class="player-dot"></i></div>', iconSize: [44, 44], iconAnchor: [22, 22] }),
                zIndexOffset: 2000, draggable: false, keyboard: false, title: 'You are here', alt: 'Your position'
            }).addTo(playerLayer);
            playerMarker.on('dragend', function () {
                var q = playerMarker.getLatLng();
                setPlayer(q.lat, q.lng, { manual: true });
                if (state.route && state.routeFor) startRoute(byId[state.routeFor], state.routeMode, { quiet: true });
            });
            accuracyCircle = L.circle(ll, { radius: 10, className: 'accuracy-circle', interactive: false }).addTo(playerLayer);
            [250, 500, 1000].forEach(function (r) {
                radarRings.push(L.circle(ll, { radius: r, className: 'radar-ring', fill: false, interactive: false }).addTo(playerLayer));
            });
        } else {
            playerMarker.setLatLng(ll);
            accuracyCircle.setLatLng(ll);
            radarRings.forEach(function (c) { c.setLatLng(ll); });
        }
        accuracyCircle.setRadius(state.accuracy && state.accuracy < 1500 ? state.accuracy : 0.1);
        if (opts.manual) { state.manualPlayer = true; playerMarker.dragging.enable(); }
        var cone = playerMarker.getElement() && playerMarker.getElement().querySelector('.player-cone');
        if (cone) {
            if (typeof opts.heading === 'number' && !isNaN(opts.heading)) { cone.style.display = 'block'; cone.style.transform = 'rotate(' + opts.heading + 'deg)'; }
            else if (!opts.keepHeading) cone.style.display = 'none';
        }
        if (state.follow) map.panTo(ll, { animate: true });
        $('#aa-locate-status').textContent = (opts.manual ? 'Position set by hand' : 'Live location' + (state.accuracy ? ' (±' + Math.round(state.accuracy) + ' m)' : ''));
        onPositionChanged();
    }

    var listTimer = null;
    function onPositionChanged() {
        clearTimeout(listTimer);
        listTimer = setTimeout(function () { renderList(refreshMarkers()); updateHud(); }, 250);
        updateNavigation();
    }

    function locate() {
        if (!navigator.geolocation) { toast('Location is not supported by this browser. Use "Set my position" and tap the map.'); return; }
        toast('Finding your location…');
        navigator.geolocation.getCurrentPosition(function (pos) {
            state.manualPlayer = false;
            if (playerMarker) playerMarker.dragging.disable();
            setPlayer(pos.coords.latitude, pos.coords.longitude, { accuracy: pos.coords.accuracy, heading: pos.coords.heading });
            map.flyTo([pos.coords.latitude, pos.coords.longitude], Math.max(map.getZoom(), 16), { duration: 0.9 });
            toast('Found you. The rings show 250 m, 500 m, and 1 km.');
            startWatch();
        }, function (err) {
            var why = err && err.code === 1 ? 'Location permission was denied.' : 'Could not get your location.';
            toast(why + ' Tap "Set my position" and then the map to place yourself.');
        }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 5000 });
    }

    function startWatch() {
        if (state.watchId !== null || !navigator.geolocation) return;
        state.watchId = navigator.geolocation.watchPosition(function (pos) {
            if (state.manualPlayer) return;
            setPlayer(pos.coords.latitude, pos.coords.longitude, { accuracy: pos.coords.accuracy, heading: pos.coords.heading });
        }, function () { /* keep the last known position */ }, { enableHighAccuracy: true, maximumAge: 3000, timeout: 20000 });
    }

    function stopWatch() {
        if (state.watchId !== null) { navigator.geolocation.clearWatch(state.watchId); state.watchId = null; }
    }

    function toggleManual(on) {
        state.manualMode = on === undefined ? !state.manualMode : on;
        $('#aa-manual').setAttribute('aria-pressed', state.manualMode ? 'true' : 'false');
        document.getElementById('facility-map').classList.toggle('picking', state.manualMode);
        if (state.manualMode) toast('Tap the map to place yourself. You can drag the marker afterwards.');
    }

    map.on('click', function (e) {
        if (!state.manualMode) return;
        stopWatch();
        setPlayer(e.latlng.lat, e.latlng.lng, { manual: true });
        toggleManual(false);
        if (state.route && state.routeFor) startRoute(byId[state.routeFor], state.routeMode, { quiet: true });
    });

    // ---- directions -----------------------------------------------------------------------------
    var destMarker = null;

    function clearRoute() {
        state.route = null; state.routeFor = null; state.stepIdx = 0;
        routeLayer.clearLayers();
        state.routeToken++;
        $('#aa-nav').hidden = true;
        var holder = $('#aa-directions'); if (holder) holder.innerHTML = '';
        if (window.speechSynthesis) window.speechSynthesis.cancel();
    }

    function startRoute(p, modeKey, opts) {
        opts = opts || {};
        state.routeMode = modeKey;
        if (!state.player) {
            var holder = $('#aa-directions');
            if (holder) holder.innerHTML = '<div class="callout">To get directions, the map needs to know where you are.' +
                '<div class="d-actions"><button type="button" class="btn" id="dir-locate">Use my location</button><button type="button" class="btn btn-light" id="dir-manual">Set it on the map</button></div></div>';
            var a = $('#dir-locate'), b = $('#dir-manual');
            if (a) a.addEventListener('click', function () { locate(); });
            if (b) b.addEventListener('click', function () { toggleManual(true); if (isNarrow()) $('#aa-detail').hidden = true; });
            return;
        }
        var token = ++state.routeToken;
        state.routeFor = p.id;
        var holder2 = $('#aa-directions');
        if (holder2) holder2.innerHTML = '<p class="meta">Finding a route…</p>';
        var from = { lat: state.player.lat, lng: state.player.lng }, to = { lat: p.lat, lng: p.lng };

        var controller = ('AbortController' in window) ? new AbortController() : null;
        var timer = setTimeout(function () { if (controller) controller.abort(); }, 12000);
        fetch(AA.routeUrl(modeKey, from, to), controller ? { signal: controller.signal } : {})
            .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
            .then(function (json) { var r = AA.parseRoute(json, modeKey); if (!r) throw new Error('no route'); return r; })
            .catch(function () { return AA.straightLineRoute(from, to, modeKey); })
            .then(function (route) {
                clearTimeout(timer);
                if (token !== state.routeToken) return; // a newer request replaced this one
                state.route = route; state.stepIdx = 0;
                drawRoute(route, p, opts);
                renderDirections(p);
                updateNavigation(true);
                loadElevation(route, token, p);
            });
    }

    function drawRoute(route, p, opts) {
        routeLayer.clearLayers();
        var latlngs = route.coords.map(function (c) { return [c.lat, c.lng]; });
        L.polyline(latlngs, { className: 'route-glow', weight: 14, opacity: 0.35, interactive: false }).addTo(routeLayer);
        L.polyline(latlngs, { className: 'route-line' + (route.straight ? ' route-straight' : ''), weight: 5, opacity: 1, interactive: false }).addTo(routeLayer);
        L.marker([p.lat, p.lng], {
            icon: L.divIcon({ className: 'dest-wrap', html: '<div class="dest">⚑</div>', iconSize: [34, 34], iconAnchor: [6, 32] }),
            interactive: false, keyboard: false
        }).addTo(routeLayer);
        if (!opts.quiet) {
            map.fitBounds(L.latLngBounds(latlngs).pad(0.15), isNarrow()
                ? { paddingTopLeft: [20, 90], paddingBottomRight: [20, 300] }
                : { paddingTopLeft: [380, 90], paddingBottomRight: [420, 40], maxZoom: 18 });
        }
    }

    function renderDirections(p) {
        var holder = $('#aa-directions');
        if (!holder) return;
        var route = state.route;
        var modes = Object.keys(AA.ROUTE_MODES).map(function (k) {
            var m = AA.ROUTE_MODES[k];
            return '<button type="button" class="chip" data-mode="' + k + '" aria-pressed="' + (k === state.routeMode ? 'true' : 'false') + '">' + m.icon + ' ' + esc(m.label) + '</button>';
        }).join('');
        var html = '<div class="mode-row" role="group" aria-label="Travel mode">' + modes + '</div>';
        if (route) {
            html += '<div class="route-summary"><strong>' + AA.formatDistance(route.distance) + '</strong> · about <strong>' + AA.formatDuration(route.duration) + '</strong>' +
                (route.straight ? '<span class="floor-warn"> Straight line only: street data could not be loaded.</span>' : '') + '</div>';
            if (state.routeMode === 'wheelchair') html += '<p class="meta">Time assumes about 3 km/h. The route follows walking paths and does not check for steps or kerbs.</p>';
            html += '<ol class="steps">' + route.steps.map(function (s, i) {
                return '<li data-step="' + i + '"><span class="step-ico" aria-hidden="true">' + esc(s.icon) + '</span><span class="step-text">' + esc(s.text) +
                    (s.distance ? '<small>' + AA.formatDistance(s.distance) + '</small>' : '') + '</span></li>';
            }).join('') + '</ol>';
            html += '<div id="aa-elev" class="elev"><p class="meta">Loading terrain…</p></div>';
            html += '<p class="disclaimer">Routes come from OpenStreetMap data and are for guidance only. They are not checked for wheelchair accessibility. Check the facility details and local conditions before you go.</p>';
            html += '<button type="button" class="btn btn-light" id="route-clear">Clear route</button>';
        }
        holder.innerHTML = html;
        $all('[data-mode]', holder).forEach(function (b) { b.addEventListener('click', function () { startRoute(p, b.dataset.mode); }); });
        var clear = $('#route-clear', holder);
        if (clear) clear.addEventListener('click', clearRoute);
        highlightStep();
    }

    function highlightStep() {
        if (!state.route) return;
        var current = Math.min(state.stepIdx + 1, state.route.steps.length - 1);
        $all('.steps li').forEach(function (li) { li.classList.toggle('current', Number(li.dataset.step) === current); });
    }

    // Top banner: the next maneuver, like a game quest tracker.
    var lastSpoken = '';
    function updateNavigation(force) {
        var banner = $('#aa-nav');
        var route = state.route;
        if (!route || !state.player) { banner.hidden = true; return; }
        var steps = route.steps;
        var prev = state.stepIdx;
        state.stepIdx = AA.advanceStep(state.player, steps, state.stepIdx);
        var dest = { lat: route.coords[route.coords.length - 1].lat, lng: route.coords[route.coords.length - 1].lng };
        var toDest = AA.haversine(state.player, dest);
        var arrived = toDest < 25;
        var next = steps[Math.min(state.stepIdx + 1, steps.length - 1)];
        var distToNext = next && next.location ? AA.haversine(state.player, next.location) : toDest;
        var offRoute = !route.straight && !arrived && AA.distanceToRoute(state.player, route.coords) > 60;

        var remaining = distToNext;
        for (var j = state.stepIdx + 2; j < steps.length; j++) remaining += steps[j].distance || 0;
        if (steps.length <= 1) remaining = toDest;

        var text, icon;
        if (arrived) { icon = '⚑'; text = 'You have arrived'; }
        else if (offRoute) { icon = '⚠'; text = 'You are off the route'; }
        else { icon = next.icon; text = next.text; }

        banner.hidden = false;
        banner.classList.toggle('warn', offRoute);
        $('#nav-icon').textContent = icon;
        $('#nav-text').textContent = text;
        $('#nav-dist').textContent = arrived ? '' : (offRoute ? 'Recalculate to get back on track' : 'In ' + AA.formatDistance(distToNext) + ' · ' + AA.formatDistance(remaining) + ' left');
        $('#nav-reroute').hidden = !offRoute;

        if (prev !== state.stepIdx || force) highlightStep();

        if (offRoute && state.watchId !== null && !state.manualPlayer && Date.now() - state.lastReroute > 20000 && AA.haversine(state.player, dest) > 40) {
            state.lastReroute = Date.now();
            if (state.routeFor) startRoute(byId[state.routeFor], state.routeMode, { quiet: true });
        }

        if (state.voice && window.speechSynthesis) {
            var phrase = arrived ? text : text + (distToNext > 30 ? ', in ' + AA.formatDistance(distToNext) : '');
            var key = (arrived ? 'A' : state.stepIdx) + '|' + (offRoute ? 'off' : 'on');
            if (key !== lastSpoken) { lastSpoken = key; window.speechSynthesis.cancel(); window.speechSynthesis.speak(new SpeechSynthesisUtterance(phrase)); }
        }
    }

    // ---- elevation profile ------------------------------------------------------------------------
    function loadElevation(route, token) {
        var box = $('#aa-elev');
        if (!box || route.straight) { if (box) box.innerHTML = ''; return; }
        var samples = AA.sampleRoute(route.coords, 40);
        if (samples.length < 2) { box.innerHTML = ''; return; }
        var url = 'https://api.open-meteo.com/v1/elevation?latitude=' +
            samples.map(function (s) { return s.lat.toFixed(5); }).join(',') + '&longitude=' +
            samples.map(function (s) { return s.lng.toFixed(5); }).join(',');
        fetch(url).then(function (r) { if (!r.ok) throw new Error(); return r.json(); }).then(function (json) {
            if (token !== state.routeToken) return;
            var elev = json.elevation;
            if (!elev || elev.length !== samples.length) throw new Error();
            var profile = samples.map(function (s, i) { return { d: s.d, elev: elev[i] }; });
            var holder = $('#aa-elev');
            if (holder) holder.innerHTML = elevationHtml(profile);
        }).catch(function () {
            var holder = $('#aa-elev');
            if (holder && token === state.routeToken) holder.innerHTML = '<p class="meta">Terrain data is not available right now.</p>';
        });
    }

    function elevationHtml(profile) {
        var W = 300, H = 84, top = 8, bottom = 8;
        var min = Math.min.apply(null, profile.map(function (p) { return p.elev; }));
        var max = Math.max.apply(null, profile.map(function (p) { return p.elev; }));
        var range = Math.max(max - min, 4); // a nearly flat route should look flat
        var total = profile[profile.length - 1].d || 1;
        var pts = profile.map(function (p) {
            return (p.d / total * W).toFixed(1) + ',' + (H - bottom - (p.elev - min) / range * (H - top - bottom)).toFixed(1);
        });
        var stats = AA.slopeStats(profile, 100);
        var cls = stats.steepest >= 8.3 ? 'elev-steep' : (stats.steepest >= 5 ? 'elev-mid' : 'elev-flat');
        var summary = stats.label + '. Climb ' + Math.round(stats.ascent) + ' m, drop ' + Math.round(stats.descent) + ' m, steepest about ' + stats.steepest.toFixed(1) + ' percent.';
        return '<h4>Terrain along the route</h4>' +
            '<svg class="elev-chart ' + cls + '" viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="' + esc(summary) + '" preserveAspectRatio="none">' +
            '<polygon points="0,' + H + ' ' + pts.join(' ') + ' ' + W + ',' + H + '" class="elev-area"/>' +
            '<polyline points="' + pts.join(' ') + '" class="elev-line"/></svg>' +
            '<div class="elev-axis"><span>' + Math.round(min) + ' m</span><span>' + AA.formatDistance(total) + '</span><span>' + Math.round(max) + ' m</span></div>' +
            '<p class="elev-summary ' + cls + '"><strong>' + esc(stats.label) + '.</strong> Climb ' + Math.round(stats.ascent) + ' m · drop ' + Math.round(stats.descent) + ' m · steepest about ' + stats.steepest.toFixed(1) + '%.</p>' +
            '<p class="meta">Estimated from coarse public elevation data (about 90 m resolution). Ramps are steeper than this shows. A grade over 8.3% (1:12) is hard for many wheelchair users.</p>';
    }

    // ---- toast ----------------------------------------------------------------------------------
    var toastTimer = null;
    function toast(msg) {
        var t = $('#aa-toast');
        t.textContent = msg; t.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { t.hidden = true; }, 5000);
    }

    // ---- wiring -----------------------------------------------------------------------------------
    function wire() {
        $('#f-q').addEventListener('input', function (e) { state.q = e.target.value.trim(); refreshAll(); });
        $('#f-category').addEventListener('change', function (e) { state.category = e.target.value; refreshAll(); });
        $('#f-need').addEventListener('change', function (e) { state.need = e.target.value; store('need', state.need); refreshAll(); });
        $('#f-min').addEventListener('change', function (e) { state.minMatch = e.target.checked; refreshAll(); });
        $('#f-sort').addEventListener('change', function (e) { state.sort = e.target.value; refreshAll(); });
        $('#f-radius').addEventListener('change', function (e) {
            state.radiusKm = Number(e.target.value);
            if (state.radiusKm > 0 && !state.player) toast('Find your location first, then the distance filter will apply.');
            refreshAll();
        });
        $('#f-reset').addEventListener('click', function () {
            state.q = ''; state.category = ''; state.features = {}; state.minMatch = false; state.radiusKm = 0; state.sort = 'auto';
            $('#f-q').value = ''; $('#f-category').value = ''; $('#f-min').checked = false; $('#f-radius').value = '0'; $('#f-sort').value = 'auto';
            $all('#f-features .chip').forEach(function (c) { c.setAttribute('aria-pressed', 'false'); });
            refreshAll();
        });
        $('#aa-list').addEventListener('click', function (e) {
            var btn = e.target.closest('.aa-item');
            if (btn) select(Number(btn.dataset.id), { focus: true });
        });
        $('#aa-locate').addEventListener('click', locate);
        $('#aa-manual').addEventListener('click', function () { toggleManual(); });
        $('#aa-follow').addEventListener('click', function () {
            state.follow = !state.follow;
            this.setAttribute('aria-pressed', state.follow ? 'true' : 'false');
            if (state.follow && state.player) map.panTo([state.player.lat, state.player.lng]);
            if (state.follow && !state.player) toast('Find your location first.');
        });
        $('#aa-layers-btn').addEventListener('click', function () {
            var menu = $('#aa-layers'); var open = menu.hidden; menu.hidden = !open; this.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        $all('input[name="basemap"]').forEach(function (r) { r.addEventListener('change', function () { setBasemap(r.value); }); });
        $('#aa-radar').addEventListener('change', function (e) { document.getElementById('aa-app').classList.toggle('no-radar', !e.target.checked); });
        $('#aa-side-toggle').addEventListener('click', function () {
            var side = $('#aa-side'); var open = !side.classList.contains('open');
            side.classList.toggle('open', open); this.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        $('#nav-end').addEventListener('click', clearRoute);
        $('#nav-reroute').addEventListener('click', function () { if (state.routeFor) startRoute(byId[state.routeFor], state.routeMode, { quiet: true }); });
        $('#nav-voice').addEventListener('click', function () {
            state.voice = !state.voice; lastSpoken = '';
            this.setAttribute('aria-pressed', state.voice ? 'true' : 'false');
            if (!state.voice && window.speechSynthesis) window.speechSynthesis.cancel(); else updateNavigation();
        });
        if (!window.speechSynthesis) $('#nav-voice').hidden = true;
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (state.manualMode) toggleManual(false);
                else if (!$('#aa-detail').hidden) closeDetail();
            }
        });
        map.on('moveend', updateHud);
    }

    // ---- start ------------------------------------------------------------------------------------
    function init() {
        var bar = document.querySelector('.topbar');
        if (bar) document.documentElement.style.setProperty('--topbar-h', bar.offsetHeight + 'px');
        setBasemap(state.basemap);
        buildFilters();
        wire();

        var params = new URLSearchParams(location.search);
        if (params.get('q')) { state.q = params.get('q'); $('#f-q').value = state.q; }
        if (params.get('category') && CFG.categories.indexOf(params.get('category')) !== -1) { state.category = params.get('category'); $('#f-category').value = state.category; }

        var visible = refreshMarkers();
        renderList(visible);
        if (visible.length && !params.get('facility')) {
            var bounds = L.latLngBounds(visible.map(function (p) { return [p.lat, p.lng]; }));
            map.fitBounds(bounds.pad(0.25), { maxZoom: 16 });
        }
        updateHud();
        var wanted = Number(params.get('facility'));
        if (wanted && byId[wanted]) select(wanted, { focus: false });
        setTimeout(function () { map.invalidateSize(); }, 150);
    }

    try {
        init();
    } catch (err) {
        fatal('Something went wrong while starting the map (see the console for details).', err);
    }
}());
