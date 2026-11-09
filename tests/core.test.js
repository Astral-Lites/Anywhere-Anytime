// Run with: node tests/core.test.js   (no packages needed)
const assert = require('assert');
const AA = require('../public/js/anywhere-core.js');

// distance: 0.01 degrees of latitude is about 1,112 m
const d = AA.haversine({ lat: 15.48, lng: 120.97 }, { lat: 15.49, lng: 120.97 });
assert(Math.abs(d - 1111.9) < 3, 'haversine ' + d);
assert.strictEqual(AA.compassName(AA.bearing({ lat: 0, lng: 0 }, { lat: 1, lng: 0 })), 'north');
assert.strictEqual(AA.compassName(AA.bearing({ lat: 0, lng: 0 }, { lat: 0, lng: 1 })), 'east');
assert.strictEqual(AA.formatDistance(120), '120 m');
assert.strictEqual(AA.formatDistance(1500), '1.5 km');
assert.strictEqual(AA.formatDuration(30), '1 min');
assert.strictEqual(AA.formatDuration(4000), '1 h 7 min');
assert.strictEqual(AA.escapeHtml('<b>"x"</b>'), '&lt;b&gt;&quot;x&quot;&lt;/b&gt;');

// needs matching
const fac = { score: 50, features: { has_ramp: true, has_elevator: false, has_handrails: true }, amenities: ['priority_seating'] };
const need = { features: ['has_ramp', 'has_elevator', 'has_handrails'], amenities: ['priority_seating'] };
let m = AA.matchScore(fac, need);
assert.strictEqual(m.pct, 75); assert.deepStrictEqual(m.missing, ['has_elevator']);
assert.strictEqual(AA.matchScore(fac, null).pct, 50);
assert.strictEqual(AA.band(75), 'good'); assert.strictEqual(AA.band(50), 'fair'); assert.strictEqual(AA.band(10), 'low');

// route parsing with a sample OSRM-shaped response
const osrm = { code: 'Ok', routes: [{ distance: 600, duration: 430, geometry: { coordinates: [[120.97, 15.48], [120.97, 15.482], [120.973, 15.482]] },
  legs: [{ steps: [
    { distance: 220, name: 'Rizal St', maneuver: { type: 'depart', bearing_after: 0, location: [120.97, 15.48] } },
    { distance: 380, name: 'Mabini Ave', maneuver: { type: 'turn', modifier: 'right', location: [120.97, 15.482] } },
    { distance: 0, name: '', maneuver: { type: 'arrive', modifier: 'left', location: [120.973, 15.482] } } ] }] }] };
let r = AA.parseRoute(osrm, 'walk');
assert.strictEqual(r.steps[0].text, 'Head north on Rizal St');
assert.strictEqual(r.steps[1].text, 'Turn right onto Mabini Ave');
assert.strictEqual(r.steps[1].icon, '→');
assert.strictEqual(r.steps[2].text, 'You have arrived. The destination is on your left');
assert.strictEqual(r.duration, 430);
let rw = AA.parseRoute(osrm, 'wheelchair');
assert(Math.abs(rw.duration - 720) < 1, 'wheelchair pace ' + rw.duration); // 600 m at 3 km/h = 720 s
assert.strictEqual(AA.parseRoute({ code: 'NoRoute' }, 'walk'), null);
assert(AA.routeUrl('cycle', { lat: 15.48, lng: 120.97 }, { lat: 15.49, lng: 120.98 }).includes('routed-bike'));
assert(AA.routeUrl('walk', { lat: 15.48, lng: 120.97 }, { lat: 15.49, lng: 120.98 }).includes('120.970000,15.480000;120.980000,15.490000'));

// fallback route
const sl = AA.straightLineRoute({ lat: 15.48, lng: 120.97 }, { lat: 15.49, lng: 120.97 }, 'walk');
assert(sl.straight && sl.steps[0].text.includes('north'));

// progress along a route
assert.strictEqual(AA.advanceStep({ lat: 15.4801, lng: 120.97 }, r.steps, 0), 0);       // still on the first stretch
assert.strictEqual(AA.advanceStep({ lat: 15.482, lng: 120.97005 }, r.steps, 0), 1);     // reached the turn
assert.strictEqual(AA.advanceStep({ lat: 15.4801, lng: 120.97 }, r.steps, 1), 1);       // never goes backwards
assert.strictEqual(AA.advanceStep({ lat: 15.482, lng: 120.973 }, r.steps, 1), 2);       // reached the end
assert(AA.distanceToRoute({ lat: 15.481, lng: 120.97 }, r.coords) < 1);
assert(AA.distanceToRoute({ lat: 15.481, lng: 120.9709 }, r.coords) > 90);

// sampling + slope
const line = []; for (let i = 0; i <= 20; i++) line.push({ lat: 15.48 + i * 0.0002, lng: 120.97 }); // ~440 m
const samples = AA.sampleRoute(line, 40);
assert(samples.length >= 2 && samples.length <= 40); assert.strictEqual(samples[0].d, 0);
assert(Math.abs(samples[samples.length - 1].d - 444.8) < 3);
const flat = AA.slopeStats([{ d: 0, elev: 10 }, { d: 120, elev: 10.5 }, { d: 240, elev: 10 }]);
assert.strictEqual(flat.label, 'Mostly flat');
const steep = AA.slopeStats([{ d: 0, elev: 10 }, { d: 100, elev: 20 }, { d: 200, elev: 30 }]);
assert(steep.steepest >= 9.9 && steep.label.startsWith('Steep')); assert.strictEqual(steep.ascent, 20);
console.log('core.test.js: all checks passed');
