# Production deployment (EC2 + RDS + ElastiCache + S3)

Follows the [Fleetbase "Deploy in Cloud" guide](https://fleetbase.io/docs/platform/quickstart/deploy-in-cloud):
Docker Compose on a single EC2 host, Nginx reverse proxy with Let's Encrypt, managed AWS services for
MySQL, Redis and file storage.

| Piece | Where |
| --- | --- |
| Console | https://fleet.unitedsoftware.lat (static Ember build served by `nginx:alpine`) |
| API | https://api.unitedsoftware.lat (`fleetbase/fleetbase-api` pinned to the digest we run locally, code bind-mounted from `/opt/united-fleetbase/{api,packages}`) |
| WebSockets | `https://api.unitedsoftware.lat/socketcluster/` → socketcluster container |
| Server | EC2 `i-0a0a5d5dc6d1c6191` (`ulogistics-production-fleetbase`, t3.large, EIP 35.173.168.4), CloudFormation stack `ulogistics-production` |
| Database | RDS MySQL 8 `ulogistics-production-mysql80` (creds in Secrets Manager `ulogistics/production/database`) |
| Cache / queue | ElastiCache Redis `ulogistics-production-redis` |
| Files | S3 `ulogistics-production-uploads-828885964402` (`FILESYSTEM_DRIVER=s3`, instance-role auth) |
| Mail | Resend (`ruteo@unitedlogistics.com.do`) |
| Stack dir on server | `/opt/united-fleetbase` (`docker-compose.yml`, `.env`, `api/`, `packages/`, `console-dist/`) |
| Deploy log on server | `/var/log/united-deploy.log` |

The host Nginx (`/etc/nginx/sites-enabled/fleetbase`) proxies `fleet.` → `127.0.0.1:4200`, `api.` → `127.0.0.1:8000`
and `/socketcluster/` → `127.0.0.1:38000`; certbot renews the certificate automatically.

## Shipping a new version

```bash
aws login                      # if the CLI session expired
deploy/ec2/release.sh          # console + API
deploy/ec2/release.sh --api-only
```

`release.sh` builds the console locally (`console.env.production` is copied to `console/environments/.env.production`),
tars `api/` + `packages/{fleetops,energyops}`, uploads everything to `s3://…/deploy/<timestamp>/`, and over SSM
swaps the code in place, runs `migrate` / `fleetbase:create-permissions` / cache clears and restarts the stack.
The previous release stays in `api.prev`, `packages.prev`, `console-dist.prev` for a quick rollback.

## Operating

```bash
aws ssm start-session --target i-0a0a5d5dc6d1c6191 --region us-east-1
cd /opt/united-fleetbase
docker compose ps
docker compose logs -f application queue scheduler
docker compose exec application php artisan tinker
```

Backups taken before the first cutover (old stock deployment data) live in `s3://…/deploy/backups/`.
RDS automated backups are on (14 days).
