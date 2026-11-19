# Vehicle Location Sync via API (no telematics hardware)

This guide explains how to feed vehicle positions into your self-hosted Fleetbase from **any external
location source** (your own GPS provider's API, a fleet-card vendor, a spreadsheet export, an MQTT
bridge, a custom phone app, etc.) instead of wiring devices into Fleetbase's telematics module.

Everything here was verified against the Fleet-Ops API source (`fleetbase/fleetops`,
`server/src/Http/Controllers/Api/v1/VehicleController.php` and `routes.php`) at the version deployed
by this repository (Fleetbase v0.8.1 images).

---

## 1. How Fleetbase stores and uses a vehicle position

| Concept | What it is |
|---|---|
| **Vehicle** | A Fleet-Ops record (`vehicle_xxxxxxx`). Has a current `location`, `speed`, `heading`, `altitude`. |
| **Driver** | A person who can be assigned to a vehicle. The Navigator app tracks the *driver*; Fleetbase copies the driver's position to the assigned vehicle. |
| **Position** | A row in the `positions` table written **every time** a track call is accepted. This is the breadcrumb history used by the console (route replay, "positions" views, metrics). |
| **Broadcast** | Every accepted track call emits the `vehicle.location_changed` socket event so the console map (and any websocket subscriber) moves the marker live. |
| **Geofences** | Every accepted vehicle track call runs geofence entry/exit/dwell detection. |
| **Current order** | If the vehicle has a driver with an active order, the position is linked to that order and its current destination, which drives ETAs and the customer tracking page. |

So "syncing a location" is one HTTP call per sample. There is no separate "position" write API; the
track endpoint does everything above.

---

## 2. Authentication

1. Open the console: `https://<your-fqdn>` -> **Developers** -> **API Keys** -> **Create new key**.
2. Give it a name (e.g. `vehicle-sync`). Environment **Live**.
3. Copy the **secret key**. It starts with `flb_live_`. (Keys starting with `flb_test_` write to the
   *sandbox* database and are invisible in the live console.)
4. Send it on every request:

```
Authorization: Bearer flb_live_XXXXXXXXXXXXXXXXXXXXXXXX
Content-Type: application/json
Accept: application/json
```

Rate limit: by default **120 requests per minute per API key** (`THROTTLE_REQUESTS_PER_MINUTE` on the
`application` container, or Settings -> API in the console). A `429` carries a `Retry-After` header.

---

## 3. The endpoint

```
POST|PUT|PATCH  https://<your-fqdn>:8000/v1/vehicles/{vehicle}/track
```

`{vehicle}` is resolved by **`public_id`** (`vehicle_abc123`) **or by `internal_id`**. That second
option is the key to a painless sync: set each vehicle's `internal_id` in Fleetbase to the ID your
location source uses (`UNIT-001`, the plate, the IMEI, whatever) and you never need a mapping table.

### Request body

| Field | Type | Required | Notes |
|---|---|---|---|
| `latitude` | number | yes | WGS-84 decimal degrees |
| `longitude` | number | yes | WGS-84 decimal degrees |
| `speed` | number | no | Stored as sent. Navigator sends **metres/second** (from the phone's GPS). Pick one unit and keep it consistent. |
| `heading` | number | no | Degrees, 0-360 |
| `altitude` | number | no | Metres |

Anything else in the body (timestamps, battery, ignition...) is **ignored** by this endpoint. The
position is stamped with the **server time** when it arrives, so send samples as close to real time
as you can and do not back-fill history through this route.

If `latitude` and `longitude` are both missing/zero the call is a no-op and simply returns the
vehicle (legacy compatibility), so a bad payload will not error, it will silently do nothing.

### Response

`200` with the vehicle resource (same shape as `GET /v1/vehicles/{id}`), including the new
`location`, `speed`, `heading`, `altitude` and `driver_id` if one is assigned.

`404 {"error":"Vehicle resource not found."}` if the id/internal_id does not match a vehicle in
the key's organization.

### Example

```bash
curl -X POST "https://<your-fqdn>:8000/v1/vehicles/UNIT-001/track" \
  -H "Authorization: Bearer flb_live_XXXX" \
  -H "Content-Type: application/json" \
  -d '{"latitude":18.4861,"longitude":-69.9312,"speed":11.2,"heading":270,"altitude":40}'
```

---

## 4. One-time setup of your vehicles

### Create a vehicle with your external id as `internal_id`

```bash
curl -X POST "https://<your-fqdn>:8000/v1/vehicles" \
  -H "Authorization: Bearer flb_live_XXXX" -H "Content-Type: application/json" \
  -d '{
    "internal_id": "UNIT-001",
    "name": "Camión 01",
    "plate_number": "A123456",
    "make": "Isuzu", "model": "NPR", "year": 2022,
    "status": "active"
  }'
```

Response includes `"id": "vehicle_..."`. Either id works for `/track`.

### Assign a driver (optional but recommended)

Positions only get linked to orders when the vehicle has a driver with an active order. In the
console: Fleet-Ops -> Drivers -> edit -> Vehicle. Or via API when creating/updating a driver:

```bash
curl -X PUT "https://<your-fqdn>:8000/v1/drivers/driver_xxx" \
  -H "Authorization: Bearer flb_live_XXXX" -H "Content-Type: application/json" \
  -d '{"vehicle":"vehicle_xxx"}'
```

(The `vehicle` field must be a `vehicle_` public id, not an internal id.)

### List vehicles (to find ids)

```bash
curl "https://<your-fqdn>:8000/v1/vehicles?limit=100" -H "Authorization: Bearer flb_live_XXXX"
```

---

## 5. The sync pattern

```
┌──────────────┐  poll every N s   ┌──────────────┐  POST /v1/vehicles/{id}/track   ┌───────────┐
│ Your source  │ ────────────────► │  sync worker │ ──────────────────────────────► │ Fleetbase │
│ (GPS vendor, │                   │ (tools/      │        1 call per vehicle       │   API     │
│  DB, MQTT…)  │                   │  sync.mjs)   │        per changed sample       └───────────┘
└──────────────┘                   └──────────────┘
```

Rules that keep this cheap and clean:

1. **Push only when something changed.** Skip a vehicle if the new sample is the same timestamp as the
   last one, or it moved less than ~5 m. The console does not benefit from identical points and the
   `positions` table grows forever otherwise.
2. **Pace it.** With the default limit of 120 req/min per key, 20 vehicles at a 15 s interval is 80
   req/min. More vehicles: raise `THROTTLE_REQUESTS_PER_MINUTE` in `docker-compose.override.yml` (and
   `docker compose up -d`), or use several API keys, or lower the frequency for parked vehicles.
3. **Retry on 429/5xx with backoff**, never on 404 (wrong id) or 401 (wrong key).
4. **Send real-time only.** If your source gives you a batch of old points, push only the latest one
   per vehicle.
5. **One worker, one key.** Run the worker as a service (systemd, a container on the same VM, an Azure
   Function on a timer, GitHub Actions cron for low frequency, etc.).

### Ready-made worker

`tools/vehicle-location-sync/sync.mjs` in this repository implements all of the above with zero
dependencies (Node 20+). Edit `fetchPositionsFromSource()` to call your source, then:

```bash
FLEETBASE_HOST=https://<your-fqdn>:8000 \
FLEETBASE_SECRET=flb_live_XXXX \
POLL_SECONDS=15 \
node tools/vehicle-location-sync/sync.mjs
```

Environment knobs: `POLL_SECONDS` (default 15), `MIN_MOVE_METERS` (5), `MAX_PER_MINUTE` (100).

#### Run it on the Fleetbase VM as a systemd service

```bash
sudo mkdir -p /opt/vehicle-sync && sudo cp sync.mjs /opt/vehicle-sync/
sudo tee /etc/systemd/system/vehicle-sync.service >/dev/null <<'UNIT'
[Unit]
Description=Fleetbase vehicle location sync
After=network-online.target docker.service

[Service]
Environment=FLEETBASE_HOST=https://<your-fqdn>:8000
Environment=FLEETBASE_SECRET=flb_live_XXXX
Environment=POLL_SECONDS=15
ExecStart=/usr/bin/node /opt/vehicle-sync/sync.mjs
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
UNIT
sudo apt-get install -y nodejs   # Ubuntu 24.04 ships Node 18+; or use NodeSource for 22
sudo systemctl enable --now vehicle-sync
journalctl -u vehicle-sync -f
```

### Minimal Python equivalent

```python
import os, time, requests

HOST = os.environ["FLEETBASE_HOST"]          # https://<fqdn>:8000
KEY  = os.environ["FLEETBASE_SECRET"]        # flb_live_...
S = requests.Session(); S.headers.update({"Authorization": f"Bearer {KEY}", "Accept": "application/json"})

def my_source():                             # -> [{"vehicle": "UNIT-001", "lat": ..., "lng": ..., "speed": ..., "heading": ...}]
    return []

last = {}
while True:
    for p in my_source():
        key = (round(p["lat"], 5), round(p["lng"], 5))
        if last.get(p["vehicle"]) == key:
            continue
        r = S.post(f"{HOST}/v1/vehicles/{p['vehicle']}/track",
                   json={"latitude": p["lat"], "longitude": p["lng"],
                         "speed": p.get("speed"), "heading": p.get("heading")}, timeout=15)
        if r.status_code == 429:
            time.sleep(int(r.headers.get("Retry-After", "5"))); continue
        r.raise_for_status(); last[p["vehicle"]] = key
    time.sleep(15)
```

---

## 6. Consuming the positions you pushed

* **Console map**: Fleet-Ops -> Live map / Vehicles. Markers update live over the socket.
* **Current position via API**: `GET /v1/vehicles/{id}` -> `location` (GeoJSON point), `speed`,
  `heading`, `altitude`, `updated_at`.
* **History**: the console's vehicle page (positions / replay) and the internal API used by the
  console (`/int/v1/fleet-ops/positions`, `.../positions/replay`, `.../positions/metrics`). These
  internal routes need a console session, not an API key.
* **Live stream (websocket)**: connect with a SocketCluster client to
  `wss://<your-fqdn>:38000/socketcluster/` and subscribe to one of:
  * `vehicle.<vehicle_public_id>` (one vehicle)
  * `company.<company_uuid>` (everything in your organization)
  * `api.<api_credential_uuid>` (events caused by one API key)

  Event name `vehicle.location_changed`, payload:

  ```json
  {"id":"event_...","event":"vehicle.location_changed","created_at":"2026-11-19 14:00:00",
   "data":{"id":"vehicle_...","plate_number":"A123456","name":"Camión 01",
           "location":{"type":"Point","coordinates":[-69.9312,18.4861]},
           "altitude":40,"heading":270,"speed":11.2,"additionalData":{}}}
  ```
* **Webhooks**: Developers -> Webhooks lets you POST Fleet-Ops events to your own URL if you want
  a push integration back out of Fleetbase.

---

## 7. Driver positions vs vehicle positions

| You have | Use | Notes |
|---|---|---|
| Drivers with the Navigator app | Nothing to do | Navigator calls `POST /v1/drivers/{id}/track` in the background; Fleetbase copies the position onto the assigned vehicle and creates a vehicle position too. |
| Vehicles with an external GPS source (this guide) | `POST /v1/vehicles/{id}/track` | Independent of drivers. If a driver is assigned, their active order gets linked automatically. |
| Both | Both | Avoid double-pushing the *same* GPS: if the driver's phone is the source, do not also sync that vehicle from another feed, or the marker will jitter between two signals. |
| A driver app of your own (not Navigator) | `POST /v1/drivers/{id}/track` with the same body | Driver tracking additionally reverse-geocodes city/country every 10 minutes and fires order-proximity triggers. |

---

## 8. Advanced: using the telematics "custom ingest" instead

Fleetbase also has a telematics module (Fleet-Ops -> Telematics) with provider integrations and a
generic ingest endpoint: `POST /v1/webhooks/telematics/ingest/{telematic_id}` accepting
`{ "devices": [...], "events": [...], "sensors": [...] }` with an optional `X-Idempotency-Key`
header. It models *devices* that you then attach to vehicles, supports sensor data (fuel, temperature,
ignition...) and retention policies.

Use it only if you need sensor/event data or want device-level management in the console. For plain
"where is the truck" syncing, the vehicle track endpoint above is simpler, has no device setup, and
feeds the same map, positions table, geofences and order linking.

---

## 9. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `404 Vehicle resource not found.` | The id is not a `vehicle_` public id or `internal_id` in the organization that owns the key. List vehicles with the same key to check. |
| `401` | Wrong/revoked key, or you used the *public* key instead of the *secret*. |
| `429` | Over 120 req/min on that key. Back off using `Retry-After`; raise the limit or spread across keys. |
| Call returns 200 but the map does not move | You sent lat/lng as strings? They are cast to float, so `"18.4"` works, but `""` becomes 0 and the call is treated as a no-op. Also check the console is connected to the socket (green dot bottom-left); see `deploy/azure` notes on `SOCKETCLUSTER_*`. |
| Position accepted but no history in replay | History reads from `positions`; make sure the key is **live** (`flb_live_`). Test keys write to the sandbox DB. |
| Marker jumps between two spots | Two sources for one vehicle (Navigator driver + external feed). Pick one. |
