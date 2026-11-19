# Fleetbase + Navigator on Azure: deployment guide

Everything runs on **one small Azure VM** with Docker Compose. No Kubernetes, no managed
database, no load balancer: that is the cheapest shape that still gives you HTTPS, live
tracking and the mobile app.

```
                 Internet
                    │
   ┌────────────────┼──────────────────────────────── Azure VM (Ubuntu 24.04, B2s_v2) ─┐
   │        Caddy (TLS, ports 80/443/8000/38000)                                       │
   │          │ :443             │ :8000                │ :38000                        │
   │      console (nginx)   application (PHP/Octane)   socket (SocketCluster)          │
   │                              │         │                                           │
   │                     queue  scheduler   │                                           │
   │                              └── MySQL 8 ── Redis                                 │
   └────────────────────────────────────────────────────────────────────────────────────┘
```

| URL | What |
|---|---|
| `https://<label>.<region>.cloudapp.azure.com` | Console (web UI) |
| `https://<label>.<region>.cloudapp.azure.com:8000` | REST API (`/v1/...` public, `/int/v1/...` console) |
| `wss://<label>.<region>.cloudapp.azure.com:38000/socketcluster/` | Live events |

TLS certificates are issued automatically by Caddy for the Azure DNS label, so you do **not**
need to buy a domain to start. When you get one, point an `A` record at the VM IP and re-run the
setup script with the new FQDN.

---

## 1. Cost (pay-as-you-go, East US, approximate)

| Item | Monthly |
|---|---|
| VM `Standard_B2s_v2` (2 vCPU, 8 GiB, burstable) | ~US$28 |
| OS disk 64 GiB Standard SSD | ~US$5 |
| Standard static public IP | ~US$4 |
| Egress | first 100 GB free |
| **Total** | **~US$37 / month** |

Cheaper options once it is running:
* `az vm resize --size Standard_B2ls_v2` (2 vCPU / 4 GiB, ~US$21): works because the console is
  a prebuilt image (no 4 GB Ember build on the VM). Add swap if you do.
* `az vm deallocate` when you are not using it (you stop paying for compute, keep disk + IP).
* Reserved instance (1 year) cuts the VM price by roughly a third.

---

## 2. Deploy (one command)

Prerequisites on your PC: Azure CLI (`az login` done), PowerShell, OpenSSH (both already on this
machine).

```powershell
cd fleetbase\deploy\azure
.\provision-azure.ps1 -DnsLabel <your-unique-label> -Location eastus
```

What it does:
1. Creates resource group `rg-fleetbase-united-ops` and an SSH key at `~/.ssh/fleetbase_azure`.
2. Creates the VM with a static public IP and DNS label `<label>.eastus.cloudapp.azure.com`.
3. Opens ports 22, 80, 443, 8000, 38000.
4. Passes `cloud-init.yaml` to the VM. cloud-init installs Docker CE, clones
   `fleetbase/fleetbase` into `/opt/fleetbase`, drops in `fleetbase-azure-setup.sh` and runs it.
5. `fleetbase-azure-setup.sh` generates secrets, writes `docker-compose.override.yml`,
   `console/fleetbase.config.json`, the Caddyfile, pulls the official images, starts everything,
   waits for MySQL, runs Fleetbase's `deploy.sh` (migrations, seeds, permissions, caches).

First boot takes 5-10 minutes. Watch it:

```powershell
ssh -i ~\.ssh\fleetbase_azure azureuser@<fqdn> 'sudo tail -f /var/log/fleetbase-setup.log'
```

The log ends with `Done.` and the three URLs. Then open `https://<fqdn>` and create the first
account: the first user becomes the instance admin and creates the organization.

Files and secrets on the VM:

| Path | Purpose |
|---|---|
| `/opt/fleetbase` | Fleetbase checkout + generated config |
| `/opt/fleetbase/deploy/azure/secrets.env` | `APP_KEY`, DB passwords, socket key (chmod 600). **Back this up.** |
| `/opt/fleetbase/docker-compose.override.yml` | All runtime env for the containers |
| `/opt/fleetbase/console/fleetbase.config.json` | Console runtime config (API/socket hosts) |
| `/opt/fleetbase/docker/database/mysql` | MySQL data directory |
| `/opt/fleetbase/api/storage/app` | Uploaded files |

---

## 3. Day-2 operations

```bash
ssh -i ~/.ssh/fleetbase_azure azureuser@<fqdn>
cd /opt/fleetbase

sudo docker compose ps                          # health
sudo docker compose logs -f application         # API logs (queue, scheduler, socket, caddy, console, database, cache)
sudo docker compose restart application queue   # after env changes
sudo docker compose exec application php artisan tinker

# Change an env var (mail, maps key, throttle...): edit the x-fleetbase-env block, then
sudo nano docker-compose.override.yml && sudo docker compose up -d

# Update Fleetbase to the latest images
sudo docker compose pull && sudo docker compose up -d && sudo docker compose exec -T application bash -c ./deploy.sh

# Re-run the installer (keeps secrets) e.g. after changing the FQDN
sudo /opt/fleetbase/deploy/azure/fleetbase-azure-setup.sh <new-fqdn>

# Database backup
sudo docker compose exec -T database sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction fleetbase' | gzip > fleetbase-$(date +%F).sql.gz
```

Things you will want soon:
* **Mail**: set `MAIL_MAILER=smtp` + `MAIL_HOST/PORT/USERNAME/PASSWORD` (Mailgun, SES, Resend...)
  in the `x-fleetbase-env` block. Until then emails (invites, password resets) are written to the
  application log only.
* **Google Maps key**: `GOOGLE_MAPS_API_KEY` in the same block (geocoding/autocomplete).
* **File storage**: local disk by default. For durability use Azure Blob with the S3-compatible
  `FILESYSTEM_DRIVER=s3` settings, or snapshot the disk.
* **Backups**: enable Azure Backup on the VM (cheapest: daily snapshot policy), plus the mysqldump
  above into a storage account.

Tear everything down: `az group delete -n rg-fleetbase-united-ops --yes`.

---

## 4. The Navigator app on your iPhone

### Option A (recommended, zero build): App Store app + instance link

The published **Fleetbase Navigator** app (App Store id 1554208255, free) can be pointed at any
self-hosted instance through a deep link the console generates.

1. Install "Fleetbase Navigator" from the App Store on the iPhone.
2. In the console, open the **Admin** area (you must be the instance admin) -> **Fleet-Ops** ->
   **Navigator App**. It shows a copyable signed link (valid 30 minutes) generated by
   `int/v1/fleet-ops/navigator/get-link-app`. Send it to the phone (AirDrop, email, or paste it
   into a QR generator).
3. Open the link / scan the QR on the phone. It redirects to
   `flbnavigator://configure?host=https://<fqdn>:8000&key=...&socketcluster_host=<fqdn>&socketcluster_port=38000&socketcluster_secure=1`
   and the app switches to your instance (toast "Instance link was successful!").
4. Create a driver in the console (Fleet-Ops -> Drivers -> New) with a phone number or email +
   password, then sign in on the phone.

The `SOCKETCLUSTER_HOST/PORT/SECURE` and `APP_URL` values that feed this link are set by
`fleetbase-azure-setup.sh`, so the generated link already points at the Azure FQDN over TLS.

### Option B: your own build (custom name/icon, TestFlight)

The Navigator source is in `navigator-app/` (fork: https://github.com/Asset-Wealth-Intelligence-System/navigator-app). Dependencies install and the test suite passes on this
machine (`corepack yarn@3.8.7 install`, `yarn jest`: 5 suites / 10 tests). iOS binaries cannot be
built on Windows, so use a cloud builder:

* **EAS Build (Expo Application Services)** works for bare React Native projects. `eas.json` is
  already in `navigator-app/`. You need an Expo account (free tier includes iOS builds) and an
  **Apple Developer Program** membership (US$99/year, required for TestFlight by Apple).
  ```bash
  cd navigator-app
  npm i -g eas-cli && eas login
  cp united-ops.env.example .env   # then fill FLEETBASE_HOST / FLEETBASE_KEY
  eas build --platform ios --profile preview      # ad-hoc install on your registered iPhone
  eas build --platform ios --profile production   # then: eas submit -p ios  (-> TestFlight)
  ```
  If `eas build` complains about missing `expo` modules, run `npx install-expo-modules@latest`
  once (adds the Expo module system to the bare project) and retry.
* **Fastlane on a macOS runner**: the repo ships `ios/fastlane` with a `deploy_testflight` lane.
  Fill `ios/fastlane/env.example` secrets as GitHub Actions secrets and run the lane on a
  `macos-latest` runner.

Notes for a custom build:
* `.env` is compiled into the binary; `FLEETBASE_HOST` must be `https://<fqdn>:8000`
  (iOS blocks plain HTTP).
* `react-native-background-geolocation` (Transistorsoft) is free on iOS; Android release builds
  need a paid license key.
* Change `APP_IDENTIFIER` in `.env` and `PRODUCT_BUNDLE_IDENTIFIER` in the Xcode project if you
  publish under your own Apple account.

---

## 5. Syncing vehicle locations from an external source

See [VEHICLE-LOCATION-SYNC.md](VEHICLE-LOCATION-SYNC.md). Short version:

```
POST https://<fqdn>:8000/v1/vehicles/<vehicle_id or internal_id>/track
Authorization: Bearer flb_live_...
{"latitude":18.4861,"longitude":-69.9312,"speed":11,"heading":270}
```

A ready-to-run worker is in `tools/vehicle-location-sync/sync.mjs`.

---

## 6. Local development note

This Windows machine has no admin rights, no WSL and no Docker, so the stack cannot be run locally
here. The Azure VM *is* the running instance. On a machine with Docker Desktop, the upstream
one-liner works: `npm i -g @fleetbase/cli && flb install-fleetbase` (or
`bash scripts/docker-install.sh` inside `fleetbase/`).
