# Production deployment (Azure, shared VM)

United Fleetbase runs on the existing Azure VM `ul-karma-sync` (resource group `ul-karma-sync-rg`,
subscription **Asset** `22f5d746-ad69-4705-b97a-d372fa1fc9f3`, East US 2, public IP `20.7.12.37`),
next to the United Sync / TopKAT sync stack. The VM was resized from `Standard_D2als_v6` (4 GB) to
`Standard_D2as_v6` (8 GB) and its OS disk grown to 64 GB for this; a 4 GB swapfile is enabled.

Why not a dedicated VM: the other two subscriptions are disabled (read-only), the Asset subscription has
no B-series quota / capacity, and self-service quota increases are refused (`ContactSupport`), so the
cheapest dedicated 4 GB VM would be ~$56/month. Sharing adds roughly $7/month (VM) + $2.4/month (disk).

| Piece | Where |
| --- | --- |
| Console | https://distribution.unitedops.app (static Ember build in `/opt/united-fleetbase/console-dist`, nginx) |
| API | https://api.distribution.unitedops.app (`fleetbase/fleetbase-api` pinned by digest, code bind-mounted from `/opt/united-fleetbase/{api,packages}`) |
| WebSockets | `https://api.distribution.unitedops.app/socketcluster/` → socketcluster container |
| Navigator APK | https://distribution.unitedops.app/navigator/ (`/opt/united-fleetbase/navigator`) |
| Database | MySQL 8 container, volume `united_mysql_data` (dbs `fleetbase`, `fleetbase_sandbox`, `fleetbase_storefront`) |
| Cache / queue | Redis 7 container |
| Edge TLS | Caddy of the `ul-karma-sync` compose project (`~/ul-karma-sync/docker-compose.prod.yml`, see `karma-sync.docker-compose.prod.yml` here) proxies the two hosts to `172.17.0.1:4200` / `:8001` / `:38000` |
| Secrets | `/opt/united-fleetbase/.env` (copy kept locally as `deploy/azure/.env.production`, gitignored) |

Fleetbase containers publish their ports only on the docker bridge gateway `172.17.0.1`, so they are
reachable by the edge Caddy but not from the internet.

## DNS (Vercel zone `unitedops.app`)

```
A  distribution      20.7.12.37
A  api.distribution  20.7.12.37
```

## Shipping a new version

```bash
deploy/azure/release.sh            # console + API
deploy/azure/release.sh --api-only
```

The script builds the console locally, tars `api/` + `packages/{fleetops,energyops}`, uploads everything and
runs `deploy-remote.sh` on the VM (unpack, migrate, `energyops:install`, cache clears, restart queue/scheduler).
The previous release stays in `api.prev` / `packages.prev` for a quick rollback.

## Operating

```bash
ssh azureuser@20.7.12.37
cd /opt/united-fleetbase
docker compose ps
docker compose logs -f application queue scheduler
docker compose exec application php artisan tinker
# database backup
set -a; . ./.env; set +a
docker compose exec -T database mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction fleetbase | gzip > backups/fleetbase-$(date +%F).sql.gz
```

The database was restored from the local development copy taken on 2026-11-20 (the AWS RDS instance
became unreachable after AWS access was lost); orders created on AWS after 2026-09-09 are not included.
