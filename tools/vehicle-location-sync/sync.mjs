#!/usr/bin/env node
/**
 * Fleetbase vehicle location sync worker.
 *
 * Polls YOUR location source (any HTTP API, database export, MQTT bridge, CSV drop...)
 * and pushes each vehicle's latest position to Fleetbase with
 *   POST {FLEETBASE_HOST}/v1/vehicles/{vehicle}/track
 *
 * Zero dependencies: Node 20+ (global fetch). Run with:
 *   FLEETBASE_HOST=https://<fqdn>:8000 FLEETBASE_SECRET=flb_live_xxx node sync.mjs
 *
 * Replace `fetchPositionsFromSource()` with your own provider call. Everything else
 * (de-dup, retry, throttling, mapping) is already handled.
 */

const FLEETBASE_HOST   = process.env.FLEETBASE_HOST   ?? 'https://localhost:8000';
const FLEETBASE_SECRET = process.env.FLEETBASE_SECRET ?? '';
const POLL_SECONDS     = Number(process.env.POLL_SECONDS ?? 15);
const MIN_MOVE_METERS  = Number(process.env.MIN_MOVE_METERS ?? 5);   // skip pushes when the vehicle has not moved
const MAX_PER_MINUTE   = Number(process.env.MAX_PER_MINUTE ?? 100);  // Fleetbase default throttle is 120 req/min per key

if (!FLEETBASE_SECRET.startsWith('flb_')) {
    console.error('FLEETBASE_SECRET must be a Fleetbase API secret key (flb_live_... or flb_test_...)');
    process.exit(1);
}

/* ------------------------------------------------------------------ */
/* 1. YOUR SOURCE: return one record per vehicle                       */
/* ------------------------------------------------------------------ */
/**
 * Must return an array of:
 *   {
 *     vehicle:   'vehicle_abc123' | '<internal_id you gave the vehicle in Fleetbase>',
 *     latitude:  18.4861,
 *     longitude: -69.9312,
 *     speed:     12.5,           // optional, metres/second
 *     heading:   270,            // optional, degrees 0-360
 *     altitude:  40,             // optional, metres
 *     recordedAt: '2026-11-19T14:00:00Z' // optional, used only for local de-dup
 *   }
 */
async function fetchPositionsFromSource() {
    // EXAMPLE: a generic JSON endpoint from your GPS provider.
    // const res = await fetch(process.env.SOURCE_URL, { headers: { Authorization: `Bearer ${process.env.SOURCE_TOKEN}` } });
    // const rows = await res.json();
    // return rows.map((r) => ({ vehicle: r.unitId, latitude: r.lat, longitude: r.lng, speed: r.speedMps, heading: r.course, recordedAt: r.time }));

    // Demo data so the script runs out of the box (remove in production):
    const t = Date.now() / 60000;
    return [
        { vehicle: 'UNIT-001', latitude: 18.4861 + Math.sin(t) * 0.01, longitude: -69.9312 + Math.cos(t) * 0.01, speed: 11, heading: (t * 10) % 360 },
    ];
}

/* ------------------------------------------------------------------ */
/* 2. Fleetbase client                                                 */
/* ------------------------------------------------------------------ */
async function fleetbase(method, path, body) {
    const res = await fetch(`${FLEETBASE_HOST}${path}`, {
        method,
        headers: {
            Authorization: `Bearer ${FLEETBASE_SECRET}`,
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'User-Agent': 'vehicle-location-sync/1.0',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const text = await res.text();
    let json;
    try { json = text ? JSON.parse(text) : null; } catch { json = { raw: text }; }
    if (!res.ok) {
        const err = new Error(`${method} ${path} -> ${res.status} ${JSON.stringify(json)}`);
        err.status = res.status;
        err.retryAfter = Number(res.headers.get('retry-after') ?? 0);
        throw err;
    }
    return json;
}

const trackVehicle = (vehicle, pos) =>
    fleetbase('POST', `/v1/vehicles/${encodeURIComponent(vehicle)}/track`, {
        latitude: pos.latitude,
        longitude: pos.longitude,
        ...(pos.speed    != null ? { speed: pos.speed }       : {}),
        ...(pos.heading  != null ? { heading: pos.heading }   : {}),
        ...(pos.altitude != null ? { altitude: pos.altitude } : {}),
    });

/* ------------------------------------------------------------------ */
/* 3. De-dup, throttle, retry                                          */
/* ------------------------------------------------------------------ */
const last = new Map(); // vehicle -> { latitude, longitude, recordedAt }

function metersBetween(a, b) {
    const R = 6371000, toRad = (d) => (d * Math.PI) / 180;
    const dLat = toRad(b.latitude - a.latitude), dLon = toRad(b.longitude - a.longitude);
    const s = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a.latitude)) * Math.cos(toRad(b.latitude)) * Math.sin(dLon / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(s));
}

function shouldPush(pos) {
    const prev = last.get(pos.vehicle);
    if (!prev) return true;
    if (pos.recordedAt && prev.recordedAt && pos.recordedAt === prev.recordedAt) return false; // same sample
    return metersBetween(prev, pos) >= MIN_MOVE_METERS;
}

let windowStart = Date.now(), windowCount = 0;
async function throttle() {
    if (Date.now() - windowStart > 60000) { windowStart = Date.now(); windowCount = 0; }
    if (windowCount >= MAX_PER_MINUTE) {
        const wait = 60000 - (Date.now() - windowStart);
        console.warn(`[throttle] local limit reached, sleeping ${Math.ceil(wait / 1000)}s`);
        await new Promise((r) => setTimeout(r, wait));
        windowStart = Date.now(); windowCount = 0;
    }
    windowCount++;
}

async function pushWithRetry(pos, attempt = 1) {
    try {
        await throttle();
        await trackVehicle(pos.vehicle, pos);
        last.set(pos.vehicle, { latitude: pos.latitude, longitude: pos.longitude, recordedAt: pos.recordedAt });
        console.log(`[ok] ${pos.vehicle} -> ${pos.latitude.toFixed(5)},${pos.longitude.toFixed(5)}`);
    } catch (err) {
        if (err.status === 404) { console.error(`[skip] ${pos.vehicle}: not found in Fleetbase (create it or fix the id)`); return; }
        if (err.status === 401) { console.error('[fatal] API key rejected'); process.exit(1); }
        if (attempt <= 3 && (err.status === 429 || err.status >= 500 || !err.status)) {
            const delay = err.retryAfter ? err.retryAfter * 1000 : 1000 * 2 ** attempt;
            console.warn(`[retry ${attempt}] ${pos.vehicle}: ${err.message}; waiting ${delay}ms`);
            await new Promise((r) => setTimeout(r, delay));
            return pushWithRetry(pos, attempt + 1);
        }
        console.error(`[error] ${pos.vehicle}: ${err.message}`);
    }
}

/* ------------------------------------------------------------------ */
/* 4. Main loop                                                        */
/* ------------------------------------------------------------------ */
async function tick() {
    let positions = [];
    try { positions = await fetchPositionsFromSource(); } catch (err) { console.error('[source] ' + err.message); return; }
    for (const pos of positions) {
        if (typeof pos.latitude !== 'number' || typeof pos.longitude !== 'number') continue;
        if (!shouldPush(pos)) continue;
        await pushWithRetry(pos);
    }
}

console.log(`vehicle-location-sync -> ${FLEETBASE_HOST} every ${POLL_SECONDS}s`);
await tick();
setInterval(tick, POLL_SECONDS * 1000);
