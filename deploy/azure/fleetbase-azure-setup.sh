#!/usr/bin/env bash
# fleetbase-azure-setup.sh - unattended Fleetbase installer for a single Ubuntu VM.
#
# Usage (as root, on the VM):
#   fleetbase-azure-setup.sh <FQDN> [ACME_EMAIL] [APP_NAME]
#
# Idempotent: re-running keeps existing secrets (deploy/azure/secrets.env),
# rewrites generated config, pulls images, (re)starts containers and runs deploy.sh.
# Mirrors fleetbase/scripts/docker-install.sh but is fully unattended and adds a Caddy TLS front.
set -euo pipefail

FQDN="${1:?FQDN required, e.g. myfleet.eastus.cloudapp.azure.com}"
ACME_EMAIL="${2:-ops@${FQDN}}"
APP_NAME="${3:-Fleetbase}"
FLEETBASE_DIR="${FLEETBASE_DIR:-/opt/fleetbase}"
DEPLOY_DIR="${FLEETBASE_DIR}/deploy/azure"
SECRETS_FILE="${DEPLOY_DIR}/secrets.env"

log() { echo "[$(date +'%H:%M:%S')] $*"; }
gen_secret() { openssl rand -hex "${1:-20}"; }

cd "$FLEETBASE_DIR"
mkdir -p "$DEPLOY_DIR"

# ---------------------------------------------------------------------------
# 1. Secrets (generated once, kept forever)
# ---------------------------------------------------------------------------
if [[ -f "$SECRETS_FILE" ]]; then
  log "Using existing secrets from $SECRETS_FILE"
  # shellcheck disable=SC1090
  source "$SECRETS_FILE"
else
  log "Generating secrets"
  APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  DB_ROOT_PASSWORD="$(gen_secret 20)"
  DB_PASSWORD="$(gen_secret 20)"
  SOCKETCLUSTER_AUTH_KEY="$(gen_secret 32)"
  {
    echo "APP_KEY=\"${APP_KEY}\""
    echo "DB_ROOT_PASSWORD=\"${DB_ROOT_PASSWORD}\""
    echo "DB_PASSWORD=\"${DB_PASSWORD}\""
    echo "SOCKETCLUSTER_AUTH_KEY=\"${SOCKETCLUSTER_AUTH_KEY}\""
  } > "$SECRETS_FILE"
  chmod 600 "$SECRETS_FILE"
fi

DB_USERNAME="fleetbase"
DB_DATABASE="fleetbase"
DATABASE_URL="mysql://${DB_USERNAME}:${DB_PASSWORD}@database/${DB_DATABASE}"

# ---------------------------------------------------------------------------
# 2. Project .env (read by docker-compose.yml for the socket/API shared values)
# ---------------------------------------------------------------------------
#    AUTH_ENABLED=true makes the API publish over the signed internal HTTP endpoint
#    (http://socket:8001) instead of dialling the public websocket; MODE=log keeps
#    accepting clients that do not send socket tokens yet (older Navigator builds).
{
  echo "SOCKETCLUSTER_AUTH_ENABLED=true"
  echo "SOCKETCLUSTER_AUTH_KEY=${SOCKETCLUSTER_AUTH_KEY}"
  echo "SOCKETCLUSTER_AUTH_MODE=log"
  echo "SOCKETCLUSTER_ORIGIN=https://${FQDN}"
} > .env
chmod 600 .env

# ---------------------------------------------------------------------------
# 3. docker-compose.override.yml
#    - all app containers share the same APP_KEY/DATABASE_URL (the base file
#      points queue/scheduler at a passwordless root, which stops existing once
#      MYSQL_ROOT_PASSWORD is set)
#    - no container publishes a host port except Caddy
#    - console uses the prebuilt image (no Ember build on the VM)
# ---------------------------------------------------------------------------
cat > docker-compose.override.yml <<YAML
x-fleetbase-env: &fleetbase-env
  APP_KEY: "${APP_KEY}"
  APP_NAME: "${APP_NAME}"
  APP_URL: "https://${FQDN}:8000"
  CONSOLE_HOST: "https://${FQDN}"
  ENVIRONMENT: "production"
  APP_ENV: "production"
  APP_DEBUG: "false"
  DATABASE_URL: "${DATABASE_URL}"
  SESSION_DOMAIN: "${FQDN}"
  MAIL_MAILER: "log"
  MAIL_FROM_ADDRESS: "no-reply@${FQDN}"
  MAIL_FROM_NAME: "${APP_NAME}"
  # Used by the console's "Link Navigator App" deep link. The API itself
  # publishes through SOCKETCLUSTER_PUBLISH_URL=http://socket:8001.
  SOCKETCLUSTER_HOST: "${FQDN}"
  SOCKETCLUSTER_PORT: "38000"
  SOCKETCLUSTER_SECURE: "true"
  OSRM_HOST: "https://router.project-osrm.org"

# Inside the containers the public FQDN resolves to the Docker host, so anything that
# dials wss://FQDN:38000 (the websocket publish path when socket auth is off) reaches
# Caddy on this VM without a NAT hairpin through the public IP.
x-fleetbase-hosts: &fleetbase-hosts
  - "${FQDN}:host-gateway"

services:
  application:
    extra_hosts: *fleetbase-hosts
    environment:
      <<: *fleetbase-env

  queue:
    extra_hosts: *fleetbase-hosts
    environment:
      <<: *fleetbase-env

  scheduler:
    extra_hosts: *fleetbase-hosts
    environment:
      <<: *fleetbase-env

  console:
    image: fleetbase/fleetbase-console:latest
    pull_policy: missing
    ports: !override []

  httpd:
    # Caddy talks to the application container directly; nginx is not needed.
    profiles: ["legacy"]

  socket:
    ports: !override []
    environment:
      SOCKETCLUSTER_OPTIONS: '{"origins":"https://${FQDN}:*,wss://${FQDN}:*"}'

  database:
    ports: !override []
    environment:
      MYSQL_ROOT_PASSWORD: "${DB_ROOT_PASSWORD}"
      MYSQL_DATABASE: "${DB_DATABASE}"
      MYSQL_USER: "${DB_USERNAME}"
      MYSQL_PASSWORD: "${DB_PASSWORD}"
      MYSQL_ALLOW_EMPTY_PASSWORD: "no"

  caddy:
    image: caddy:2
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
      - "8000:8000"
      - "38000:38000"
    volumes:
      - ./deploy/azure/Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      - application
      - console
      - socket

volumes:
  caddy_data:
  caddy_config:
YAML
chmod 600 docker-compose.override.yml

# ---------------------------------------------------------------------------
# 4. Caddyfile + console runtime config + api/.env
# ---------------------------------------------------------------------------
sed -e "s/__FQDN__/${FQDN}/g" -e "s/__ACME_EMAIL__/${ACME_EMAIL}/g" \
  "${DEPLOY_DIR}/Caddyfile.template" > "${DEPLOY_DIR}/Caddyfile"

cat > console/fleetbase.config.json <<JSON
{
  "API_HOST": "https://${FQDN}:8000",
  "API_NAMESPACE": "int/v1",
  "SOCKETCLUSTER_HOST": "${FQDN}",
  "SOCKETCLUSTER_PORT": "38000",
  "SOCKETCLUSTER_SECURE": "true",
  "SOCKETCLUSTER_PATH": "/socketcluster/",
  "OSRM_HOST": "https://router.project-osrm.org"
}
JSON

if [[ -d api/.env ]]; then rm -rf api/.env; fi
if [[ ! -f api/.env ]]; then
  echo "# Fleetbase API environment overrides. docker-compose.override.yml takes precedence." > api/.env
fi
mkdir -p api/storage/app
chown -R 33:33 api/storage/app 2>/dev/null || true

# ---------------------------------------------------------------------------
# 5. Start
# ---------------------------------------------------------------------------
log "Pulling images"
docker compose pull
log "Starting containers"
docker compose up -d --remove-orphans

log "Waiting for database"
DB_CONTAINER=$(docker compose ps -q database)
for _ in $(seq 1 90); do
  if [[ "$(docker inspect -f '{{.State.Health.Status}}' "$DB_CONTAINER" 2>/dev/null)" == "healthy" ]]; then break; fi
  sleep 2
done
sleep 5
docker compose exec -T database mysql -uroot -p"${DB_ROOT_PASSWORD}" \
  -e "GRANT ALL PRIVILEGES ON *.* TO '${DB_USERNAME}'@'%'; FLUSH PRIVILEGES;"

log "Waiting for application container"
for _ in $(seq 1 60); do
  if docker compose exec -T application php -r 'exit(0);' >/dev/null 2>&1; then break; fi
  sleep 2
done

log "Running deploy.sh (migrations, seeds, permissions, caches)"
docker compose exec -T application bash -c "./deploy.sh"
docker compose up -d
docker compose restart queue scheduler >/dev/null 2>&1 || true

log "Done."
echo
echo "  Console : https://${FQDN}"
echo "  API     : https://${FQDN}:8000"
echo "  Socket  : wss://${FQDN}:38000/socketcluster/"
echo
echo "  Secrets : ${SECRETS_FILE}"
