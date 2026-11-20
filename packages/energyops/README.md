# EnergyOps

Compartmentalized oil and gas dispatch for Fleetbase. FleetOps stays the TMS (orders, vehicles, places, drivers, routing); this extension owns the energy catalog, tank compartments, customer tanks, compartment-aware load plans and meter-fill delivery tickets for GLP / WOP / fuel distributors.

Two halves, one extension — the [Fleetbase extension anatomy](https://fleetbase.io/docs/extension-development/getting-started/extension-anatomy):

- `addon/` — Ember engine (`@fleetbase/energyops-engine`), mounted at `/energy-ops`
- `server/` — Laravel package (`fleetbase/energyops-api`), API at `energyops/int/v1` (console) and `energyops/v1` (API key)
- `extension.json` — registry manifest

## Domain model

Every EnergyOps record is company scoped and references real FleetOps entities by foreign key:

| EnergyOps | Ties to FleetOps | Purpose |
| --- | --- | --- |
| Operation | `Place` (depot) | GLP / WOP business line, unit, split-delivery and ullage rules; fallback loading station |
| Product | Operation | Propane, butane, diesel… density, hazmat class, default unit |
| Compatibility rule | Product ↔ Product / Operation | allowed · flush required · forbidden when switching products in a compartment |
| Vehicle profile | `Vehicle`, `Asset` (coupled trailer), `Place` (default depot) | operation, tractor flag, pump/meter data, habitual loading station |
| Compartment | `Vehicle` or `Asset` owner, dedicated Product | capacity, max fill %, residual, requires-flush |
| Customer tank | `Place`, Product | capacity, safe fill %, last known volume, reorder point → ullage |
| Load plan → load lines → stop draws | `Vehicle`, `Order`, `Entity`, `Manifest`, `Place` (depot), `File` (proof of load) | what goes in which compartment for which order, where it was loaded, factura / boleta de carga |
| Delivery ticket | `Order`, stop draw, compartment, customer tank | metered quantity, seals, meter readings, Dynamics invoice number, exceptions |
| Unload (descarga) → unload lines | `Vehicle`, `Driver`, load plan, `Manifest`, `Place` (inventory), customer tank (inventory), `File` (photo / signature) | end-of-day remnant returned to inventory per compartment |

Places play three roles: customer sites (default), **depots** (`places.type = depot`, loading stations) and the **inventory site** (`places.type = inventory`, where trucks unload). Set the role from the place form (Energy Ops · Site role) together with the site's `meta.receiving_hours`.

Energy demand lives on FleetOps order **entities** as `meta.energyops_product_uuid`, `meta.volume`, `meta.unit` and `meta.energyops_customer_tank_uuid`; the order-form slot writes these and the packer reads them.

## Console

- **Energy Ops** header item → dashboard, Catalog (operations, products, compatibility), Fleet (tank configuration, customer tanks), Dispatch (load plans, delivery tickets, descargas).
- Lists use `Layout::Resource::Tabular`; every create / edit / view opens in the host `ResourceContextPanel` and is deep-linkable (`/energy-ops/products/new`, `/energy-ops/load-plans/<id>`).
- FleetOps slots: compartment editor on the vehicle form, compartment inventory + *Registrar descarga* on vehicle details, customer tanks and site role / receiving hours on the place form, energy fields on order entities (GLP product preselected and locked for `glp-delivery`), time-window preset from the drop-off's receiving hours on the order form, load plan + tickets on order details, and the compartment packer toggle on the orchestrator settings page.
- Settings → Energy Ops (virtual page): enable, allocation engine, safety constraints (incl. *require proof of load* / *require proof of descarga*), inventory site, order config installation.

## Backend

- `EnergyOpsServiceProvider extends CoreServiceProvider` — observers, commands, expansions (`Vehicle::energyProfile()`, `Vehicle::compartments()`, `Asset::compartments()`, `Place::customerTanks()`, `Order::loadLines()/deliveryTickets()`, `Entity::energyProduct()`), routes, migrations, config and the `compartment` orchestration engine.
- Per-table migrations plus `add_foreign_keys_to_energyops_tables` (FKs to `companies`, `users`, `vehicles`, `assets`, `places`, `orders`, `entities`, `manifests`, `manifest_stops`).
- Form Requests in `Http/Requests`, JSON resources in `Http/Resources/v1`, filters in `Http/Filter`.
- Custom actions: `compartments/for-owner|reorder|{id}/flush`, `load-plans/{id}/confirm-load|close|cancel|reload|add-orders|on-board-stock`, `load-plans/for-order|for-vehicle`, `delivery-tickets/{id}/void|exception`, `unloads/prefill`, `unloads/{id}/confirm|void`, `unloads/for-vehicle|for-load-plan`, `allocations/allocate`, `analytics/variance|utilization`, `fleet/summary`, `settings`, `summary`.
- Public API (API key, for Navigator / plant / inventory devices): `energyops/v1/load-plans` (`{id}/confirm-load`, `{id}/add-orders`, `{id}/files` upload) and `energyops/v1/unloads` (`prefill`, create, `{id}/confirm`, `{id}/void`, `{id}/files` upload).
- Services: `CompartmentPacker` (demand → compartments honouring dedicated products, residuals and compatibility rules), `AllocationService` + `CompartmentAllocationEngine` (pack, then route with VROOM), `LoadPlanService` (packs, depot, proof of load, manifest stop wiring, add late pedidos from on-board stock), `TicketService`, `UnloadService` (descarga prefill / confirm / void), `ReloadService`, `AnalyticsService`.
- Observers: `OrderObserver` (energy orders are `pod_required`), `PayloadObserver` (pickup ← operation depot, return ← inventory site when empty).

## Link for development

Official workflow is [Development Setup](https://fleetbase.io/docs/platform/quickstart/development-setup) + [Extension Quickstart](https://fleetbase.io/docs/extension-development/getting-started/quickstart).

From the repo root:

```bash
node scripts/package-linker.mjs enable energyops
node scripts/package-linker.mjs install energyops
```

Or the same links by hand:

- `api/composer.json` — path repo `../packages/energyops`, require `fleetbase/energyops-api`
- `console/package.json` — `"@fleetbase/energyops-engine": "link:../packages/energyops"`
- `console/pnpm-workspace.yaml` — `../packages/energyops`
- `packages/ember-core/addon/utils/load-installed-extensions.js` — `@fleetbase/energyops-engine` in `CORE_ENGINES`

Nothing else in `console/` is edited by hand: the extension indexer generates `console/app/extensions/energy-ops.js`, the router mount and `public/extensions.json` from `addon/extension.js` and `package.json`.

Then:

```bash
docker compose exec application composer install
docker compose exec application php artisan migrate
docker compose exec application php artisan energyops:install --enable   # order configs + permissions
docker compose exec application php artisan energyops:seed-united        # optional demo data
docker compose exec application php artisan octane:reload
```

### Importing real data

`energyops:import` loads a normalised JSON file (operations + products, customers → places → customer tanks,
vehicle profiles + compartments for trucks that already exist in FleetOps, drivers). It is idempotent — every
record is matched on a business key (`code`, customer `internal_id`, place `_import_id`, tank `serial_number`,
compartment `code` per vehicle, driver name) — so it can be re-run after fixing the source.

```bash
docker compose cp import.json application:/tmp/import.json
docker compose exec application php artisan energyops:import /tmp/import.json --dry-run       # report only (rolled back)
docker compose exec application php artisan energyops:import /tmp/import.json --purge-dummy   # wipe demo data first
```

`--purge-dummy` hard-deletes the company's Energy Ops rows, orders, places, contacts, drivers and assets; vehicles,
fleets and users are kept. Vehicles in the file are matched by `call_sign` / `plate_number`; anything not already in
FleetOps (telematics) is skipped and reported, and telematics-owned fields (plate, make/model/year, odometer) are never overwritten.

### Order flow (what has to exist before an order can be packed)

1. **Operation** (`GLP`, `WOP`) with a **depot place** — the fallback pickup of every order. Additional loading stations are
   places with `type = depot`; each vehicle profile can carry its *habitual* depot (UG12 → Sosúa, UG23 / UG69 → Cabeza de Toro,
   the rest of the GLP fleet → Refidomsa) which presets the load plan depot without restricting it.
2. **Products** under the operation with `default_unit` and `density` (kg ↔ L conversion for LPG).
3. **Vehicle profile + compartments** on each truck (`capacity_volume`, `unit`, `max_fill_percent`). A truck without
   compartments is never feasible (`no_compartments`).
4. **Customer place** (drop-off) with **customer tanks**; tanks with `capacity_volume = 0` are "unsurveyed" and skip the ullage check.
5. **Order** created with the `GLP Delivery` / `WOP Delivery` order config: pickup = depot, drop-off = customer place, one
   `bulk-energy` entity per product with `meta.energyops_product_uuid`, `meta.volume`, `meta.unit` and optionally
   `meta.energyops_customer_tank_uuid`. The vehicle assigned on the order is tried first by the packer.
6. **Pack** (order panel → *Armar ahora* or Energy Ops → Despacho → *Armar cargas*) → one load plan per vehicle for N pedidos,
   depot preset from the vehicle (optionally overridden for the whole run). The order pickup is re-pointed to that depot.
7. **Confirm load** at the plant (load plan actions) → `loaded`; pick the real loading station, enter factura / boleta numbers and
   attach the **comprobante de carga** (photos or PDF, `File.subject_type = energyops:load-plan`). With
   `safety.require_load_proof` (default on) the confirmation is rejected without an attachment.
8. **Route**: Orchestrator commit produces one Manifest per vehicle with N single-drop-off stops; `LoadPlanService::attachManifest`
   links each stop draw to its manifest stop. Each pedido keeps its own tracking and POD: the GLP / WOP flows require a
   **photo** at `delivering` (printed Dynamics invoice / meter) and a **signature** at `completed`.
9. **Late pedido** (arrives after the morning load): load plan → *Agregar al camión cargado* (`load-plans/{id}/add-orders`)
   allocates it against the uncommitted product on board — no reload trip.
10. **Issue ticket** from the order panel: metered vs planned quantity, meter readings, seals and the Dynamics invoice number. The
    variance is pushed back to the compartment residual and the customer tank level.
11. **Descarga** at the end of the day (Energy Ops → Despacho → Descargas, load plan → *Registrar descarga*, vehicle panel, or
    `energyops/v1/unloads` from Navigator): per compartment *antes / descargado / quedó* (prefilled from residuals, remaining
    usually 0), physical `unloaded_at` vs platform `created_at`, photo + signature proof (`safety.require_unload_proof`).
    Confirming empties the compartments to the remaining volume, closes the open load plan and credits the inventory tank at
    the inventory site (`places.type = inventory`, Settings → Energy Ops → Sites). Voiding reverses all of it.

Business Central / Dynamics: the invoice is handled as *photo of the printed invoice + invoice number* for now; the core
`order.completed` webhook event carries the order with its proofs for a later ERP sync.

## Console (develop mode)

**Path A — Docker** (ember serve inside the `console` container). Requires a working `pnpm` in the image; on Apple Silicon the stock Alpine `pnpm` can fail with `no @pnpm/exe.linux-arm64 native binary`. Use Path B in that case.

```bash
docker compose up -d --build console
```

**Path B — local Ember:**

```bash
docker compose stop console
cd packages/energyops && pnpm install
cd ../../console && pnpm install && pnpm start:dev
```

`addon/extension.js` registers the header item, the settings page, the dashboard widget and the FleetOps slots at boot. After ember starts, Energy Ops appears in the console header at `/energy-ops`.

## Lint

```bash
cd packages/fleetops && npx eslint ../energyops/addon ../energyops/app && npx ember-template-lint ../energyops/addon
for f in $(git ls-files packages/energyops/server | grep '\.php$'); do php -l $f; done
```
