# EnergyOps — Fleetbase Extension Guide

Working instructions for agents changing EnergyOps. Official source of truth: [Fleetbase Extension Development](https://fleetbase.io/docs/extension-development/).

EnergyOps is a **standalone Fleetbase extension** (`packages/energyops`). FleetOps stays the TMS. Do not fork FleetOps. Do not edit the plan file. Do not put `karma_sid` or live API keys in git.

## Official docs (read these, do not invent a second contract)

| Topic | URL |
| --- | --- |
| Hub | https://fleetbase.io/docs/extension-development/ |
| Quickstart | https://fleetbase.io/docs/extension-development/getting-started/quickstart |
| Anatomy | https://fleetbase.io/docs/extension-development/getting-started/extension-anatomy |
| Architecture | https://fleetbase.io/docs/extension-development/architecture/overview |
| Registration | https://fleetbase.io/docs/extension-development/architecture/extension-registration |
| Universe | https://fleetbase.io/docs/extension-development/universe-service/overview |
| Menu / Hook / Widget / Registry | under https://fleetbase.io/docs/extension-development/universe-service/ |
| Routing | https://www.fleetbase.io/docs/extension-development/frontend/routing |
| Templates | https://www.fleetbase.io/docs/extension-development/frontend/templates-and-components |
| Virtual routes | https://fleetbase.io/docs/extension-development/frontend/virtual-routes |
| Service provider | https://fleetbase.io/docs/extension-development/backend/service-provider |
| Routes & controllers | https://fleetbase.io/docs/extension-development/backend/routes-and-controllers |
| Platform dev setup | https://fleetbase.io/docs/platform/quickstart/development-setup |

Some older `/frontend-development/...` URLs 404. Use `/frontend/routing` and `/backend/service-provider`.

## What a Fleetbase extension is

One package, two halves:

1. **Ember engine** (`addon/`) — console UI
2. **Laravel package** (`server/`) — API

Tied together by `extension.json`. You import from `@fleetbase/ember-core` and `fleetbase/core-api`. You do **not** modify those packages unless United is patching platform core on purpose.

```
packages/energyops/
├── extension.json          # manifest (engine + api package files)
├── package.json            # @fleetbase/energyops-engine
├── composer.json           # fleetbase/energyops-api
├── addon/extension.js      # ONLY registration entry
├── addon/engine.js         # Engine class + shared host services
├── addon/routes.js         # Engine route map (buildRoutes)
├── config/environment.js   # modulePrefix = package name (package root, NOT addon/config)
└── server/src/
    ├── Providers/EnergyOpsServiceProvider.php
    ├── routes.php
    └── ...
```

## Boot lifecycle (do not fight this)

**Console**

1. `ExtensionManager` dynamic-imports each `addon/extension.js`
2. Calls `setupExtension(app, universe)` **before any engine loads** — header items, widgets, hooks, registries
3. First navigation to `/energy-ops` lazy-loads `addon/engine.js`
4. Optional `onEngineLoaded(engineInstance, universe, app)` runs then

**Laravel**

1. Composer discovers `EnergyOpsServiceProvider`
2. Provider loads routes, migrations, observers, expansions

## Frontend contract

### `addon/extension.js` — registration only

Export a default object with `setupExtension(app, universe)` (optionally `onEngineLoaded`).

- Header item route is a **host** route: `console.energy-ops`
- Shortcuts use host routes: `console.energy-ops.products`, …
- Inject into FleetOps **only** via `universe.whenEngineLoaded('@fleetbase/fleetops-engine', …)` and `ExtensionComponent`
- Do **not** put menu/widget/hook registration in `engine.js` or instance initializers

### `addon/engine.js` — engine only

Copy the official pattern:

```js
import config from './config/environment';
import { services, externalRoutes } from '@fleetbase/ember-core/exports';

const { modulePrefix } = config;
```

- `config` must be the **compiled object** from package-root `config/environment.js`
- Never add `addon/config/environment.js` — it shadows the compiled config, `modulePrefix` becomes `undefined`, and the engine renders a blank page
- Reuse `services` / `externalRoutes` from `@fleetbase/ember-core/exports`. Do not hand-roll the list
- `sidebar-navigator` is **not** a shared host service. Do not use `<Layout::Sidebar::Navigator>` unless that service is available in the engine. Use `<Layout::YieldSidebar>` + `<Layout::Sidebar::Item>`

### `config/environment.js`

Lives at the **package root**. Ember-cli compiles it and `addon/engine.js` imports `./config/environment` as `{ modulePrefix, environment }`.

```js
module.exports = function (environment) {
    return { modulePrefix: name, environment };
};
```

`package.json` `"name"` is `@fleetbase/energyops-engine`. `"fleetbase.route"` is `energy-ops`.

### Routing

- Route map: `addon/routes.js` via `buildRoutes` from `ember-engines/routes`
- In-engine `LinkTo` / sidebar `@route` names are **relative**: `home`, `products`, `products.edit`
- Host / header routes are `console.energy-ops…`
- Leave the engine with `LinkToExternal` / `transitionToExternal('console.…')`
- Auth is already done by the console. Gate with `abilities` using `{extension} {action} {resource}` (e.g. `energy-ops view product`)

Mount: `@fleetbase/energyops-engine` → `console.energy-ops` → `/energy-ops`.

### Templates

- `addon/templates/application.hbs` is the engine chrome: `<Layout::YieldSidebar><Layout::EnergyOpsSidebar /></Layout::YieldSidebar>` + `<Layout::Section::Container>{{outlet}}</Layout::Section::Container>`
- Sidebar is built from `Layout::Sidebar::Panel` + `Layout::Sidebar::Item` (no `Navigator`, no raw `<a>`)
- Components live under `addon/components/` (Octane / Glimmer), prefixed `energy-*` (`energy-product/form`, `energy-compartment/editor`, `energy-fleet-ops/*` slot wrappers, `energy-settings/*`, `energy-widget/*`, `cell/energy-*`)
- Re-export **models, adapters, serializers, services and every component** from `app/` so the host `ResourceContextPanel`, `modalsManager` and Ember Data can resolve them
- Do **not** re-export `app/templates/application.js` or other engine routes/templates — those merge into the host console and collide with `@fleetbase/console/templates/application`

### Lists, panels and actions (FleetOps pattern)

- Index pages: route `store.query('energy-<model>', params)` with `page/limit/sort/query` (+ filter) query params; controller defines `columns`, `actionButtons`, `bulkActions`; template renders `<Layout::Resource::Tabular @resource="energy-<model>" @data={{@model}} @columns={{this.columns}} ... />`
- Nested `index/new`, `index/details`, `index/edit` routes render `<Layout::Resource::Panel>` so every panel is deep-linkable
- One `*-actions` service per resource extends `EnergyResourceActionService` (`addon/services/energy-resource-action.js`, itself on `@fleetbase/ember-core/services/resource-action`). Panels open through `this.resourceContextPanel.open({ content: 'energy-product/form', useDefaultSaveTask: true, ... })`; never hand-roll overlays
- Custom backend actions go through `postAction()` / `getAction()` on the action service; push responses with `this.store.pushPayload('energy-<model>', response)` (the model name is required for `energy-*` payload keys)
- Cross-engine navigation (e.g. to a FleetOps order) uses `hostRouter.transitionTo('console.fleet-ops.…')`
- Form inputs: `InputGroup`, `Select` with option lists from `addon/utils/energy-options.js`, `ModelSelect` (`@modelName="vehicle"|"asset"|"place"|"energy-product"`), `Toggle`; copy via `intl` (`translations/en-us.yaml`); gate with `cannot-write` / `abilities`

### Ember Data

- Model names are `energy-*` (`energy-product`, `energy-compartment`, …) to avoid the Storefront `product` collision. `adapters/energy.js` sets `namespace = 'energyops/int/v1'` and strips the prefix; `serializers/energy.js` handles `energy-` payload keys with `EmbeddedRecordsMixin`
- Relationships are snake_case and match the API (`dedicated_product`, `coupled_asset`, `vehicle`, `place`, `order`)
- Filter params that would collide with reserved query params get a suffix (`order_id`, not `order`)

### Cross-engine UI

Pass `new ExtensionComponent('@fleetbase/energyops-engine', 'component-name')` into a registry slot. The host renders it with `LazyEngineComponent`. Do not import FleetOps templates.

Engines are isolated containers: a slot component rendered inside Fleet-Ops is instantiated with the *Fleet-Ops* owner, so its `@service compartmentActions` and `<EnergyCompartment::Editor />` would not resolve. Every `energy-fleet-ops/*` component therefore calls `registerEnergyOps(owner)` (`addon/utils/register-energy-ops.js`) in its constructor, which idempotently registers the Energy Ops components/services it renders into that owner (same pattern as Fleet-Ops' `registerComponent`). Add any new component/service used by a slot tree to that util. Components rendered by the host itself (resource context panel, modals, dashboard widgets) resolve through the `app/` re-exports and need nothing extra.

## Backend contract

- Extend `Fleetbase\Providers\CoreServiceProvider`, not Laravel's `ServiceProvider`
- Routes: `server/src/routes.php` via `loadRoutesFrom`
- Migrations: `server/migrations/` via `loadMigrationsFrom` — one migration per table (`create_energyops_<table>_table`) plus `add_foreign_keys_to_energyops_tables`; every table has `uuid`, `public_id`, `company_uuid`, `created_by_uuid`, `meta`, `status`, soft deletes
- Console (session) routes: middleware `fleetbase.protected`, prefix `energyops/int/v1`, registered with `$router->fleetbaseRoutes('products', fn ($router, $controller) => …custom actions…)`
- Public API-key routes: `fleetbase.api`, prefix `energyops/v1`, explicit `query/find/create/update/delete/bulkDelete` on `Http/Controllers/Api/v1/*` (extend `ApiResourceController`)
- Internal controllers: `Http/Controllers/Internal/v1/*` extend `EnergyOpsController` (`FleetbaseController`, `$namespace = '\\Fleetbase\\EnergyOps'`) and declare `$createRequest` / `$updateRequest`
- Validation: `Http/Requests/Create<Model>Request` / `Update<Model>Request` extending `FleetbaseRequest` (permission check in `authorize()`). Every composite unique index has a matching `Rule::unique` (via `Requests/Concerns/ResolvesUpdatedRecord`) so duplicates come back as validation errors, not SQL errors
- Serialization: `Http/Resources/v1/<Model>` extending `FleetbaseResource`; internal payloads expose `uuid`/`*_uuid`, public payloads expose `public_id`
- Filtering: `Http/Filter/<Model>Filter` extending `EnergyOpsFilter`; add both `relation($value)` and `relationUuid($value)` methods for each relation
- Models: `HasUuid`, `HasPublicId`, `HasApiModelBehavior`, `HasMetaAttributes`, `Searchable`, `SoftDeletes`; `$filterParams`, `$searchableColumns`, `$with`, `$appends`
- Expansions: `registerExpansionsFrom(__DIR__ . '/../Expansions')` — expansion methods are **methods**, not attribute accessors (`$vehicle->energyProfile()`, `$place->customerTanks()`, `$entity->energyProduct()`); `get*Attribute` closures are not seen by Eloquent
- Company uuid from `session('company')`; every query, lookup and custom action is scoped by `company_uuid`
- `HasApiModelBehavior` fills `company_uuid` / `created_by_uuid` from the session on create; `DeliveryTicket::$creationMethod` routes console creates through `TicketService::issue()`

EnergyOps API namespace used by the Ember `fetch` service: `energyops/int/v1`.

## Official local linking (United monorepo)

Do **not** hardcode EnergyOps into console source by hand. Link it, then let the indexer generate mounts.

1. `api/composer.json` — path repo `../packages/energyops` + require `fleetbase/energyops-api`
2. `console/package.json` — `"@fleetbase/energyops-engine": "link:../packages/energyops"`
3. `console/pnpm-workspace.yaml` — `../packages/energyops`
4. `packages/ember-core/addon/utils/load-installed-extensions.js` — include `@fleetbase/energyops-engine` in `CORE_ENGINES` (same preinstall list as Ledger)
5. `cd packages/energyops && pnpm install` before console install
6. `docker compose exec application composer install` then Octane reload for PHP

Indexer output (do not hand-edit unless debugging):

- `console/app/extensions/energy-ops.js`
- `console/app/router.js` mount `as: 'energy-ops', path: 'energy-ops'`
- `console/public/extensions.json`

Verify with `flb-package-linker list` — EnergyOps should show `source=extension.json`.

### Console run modes

- **Path B (this machine):** `pnpm start:dev` in `console/` → http://localhost:4200
- **Path A (Docker Ember):** `Dockerfile.server-build` — currently broken on Apple Silicon (`no @pnpm/exe.linux-arm64`)

PHP lives in `united-fleetbase-application-1`. Packages are mounted at `/fleetbase/packages`. Reload Octane after PHP edits:

```
docker compose exec application php artisan octane:reload
```

## EnergyOps domain rules

- FleetOps = TMS / VROOM / orders / vehicles / places
- EnergyOps = products, tanks, compartments, load plans, tickets, pack-then-VROOM
- Allocation engine key: `compartment` (`EnergyOps::ENGINE_ID`)
- Order configs: `energyops:glp-delivery`, `energyops:wop-delivery`
- Energy demand is stored on order entities as `meta.energyops_product_uuid`, `meta.volume`, `meta.unit`, `meta.energyops_customer_tank_uuid`
- Compartments belong to a `vehicle` or `asset` (`owner_type`/`owner_uuid` mirrored into `vehicle_uuid`/`asset_uuid`); a tractor profile with `coupled_asset_uuid` loads its own cells plus the trailer's
- Settings live in `Setting` keys `company.<uuid>.energyops.enabled`, `company.<uuid>.energyops.safety` (incl. `require_load_proof`, `require_unload_proof`), `company.<uuid>.energyops.inventory_place_uuid`, `company.<uuid>.fleet-ops.allocation-settings`
- Places: `type = depot` (loading station) / `type = inventory` (descarga site); `meta.receiving_hours {start,end}` prefills order time windows. Vehicle profile `default_depot_place_uuid` presets (never restricts) the load plan `depot_place_uuid`
- Proof attachments are core `File`s: `subject_type = energyops:load-plan` (`load_invoice|load_ticket|load_photo`) and `energyops:unload` (`unload_photo|unload_signature`); the models are preloaded in the service provider so the polymorphic type resolves
- Volumes: load lines / stop draws / tickets default to liters, compartments and unloads keep their native unit (`gal` for GLP); always convert with `EnergyOps::toLiters` / `Compartment::fromLiters`
- Descarga = `Unload` + `UnloadLine` (`draft → confirmed | voided`), handled only through `UnloadService` (side effects on compartments, load plan and inventory tank)
- A late pedido joins a loaded truck through `LoadPlanService::addOrders` (draws from `onBoardStock`), never through a new load plan
- Compatibility rules: `allowed` / `flush_required` / `forbidden`; `Compartment::flush()` clears residual + last product
- Do not depend on Pallet or PetroApp
- Do not copy FleetOps VROOM source into EnergyOps

## Blank-engine checklist

If `/energy-ops` is blank:

1. Confirm `addon/config/environment.js` does **not** exist
2. Confirm `addon/engine.js` `modulePrefix` is `@fleetbase/energyops-engine` (compiled config object, not the ENV function)
3. Confirm `application.hbs` still renders `{{outlet}}` even if sidebar chrome fails
4. Confirm header route is `console.energy-ops` and in-engine links are relative
5. Confirm the engine is in `console/public/extensions.json` and `CORE_ENGINES`
6. Hard-refresh the Ember dev server after engine config changes
