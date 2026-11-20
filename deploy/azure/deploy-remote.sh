#!/usr/bin/env bash
# Runs ON the VM as azureuser. Expects /opt/united-fleetbase/{incoming/code.tar.gz, console-dist, dump/*.sql.gz, .env, docker-compose.yml, ...}
set -euo pipefail
cd /opt/united-fleetbase
set -a; . ./.env; set +a
echo "==> unpack code"
rm -rf api.new packages.new && mkdir api.new packages.new
tar -xzf incoming/code.tar.gz -C api.new --strip-components=1 api
tar -xzf incoming/code.tar.gz -C packages.new --strip-components=1 packages
mkdir -p api.new/storage/logs api.new/storage/framework/{cache/data,sessions,views,testing} api.new/storage/app/public
if [ -d api ]; then cp -a api/storage/app/. api.new/storage/app/ 2>/dev/null || true; rm -rf api.prev packages.prev; mv api api.prev; mv packages packages.prev; fi
mv api.new api && mv packages.new packages
cp .env api/.env
sudo chown -R 33:33 api/storage api/bootstrap/cache 2>/dev/null || true
sudo chmod -R ug+rwX api/storage api/bootstrap/cache
echo "==> start database + cache"
docker compose up -d database cache
for i in $(seq 1 30); do docker compose exec -T database mysqladmin ping -uroot -p"$MYSQL_ROOT_PASSWORD" --silent 2>/dev/null && break; sleep 3; done
if [ -d dump ] && [ ! -f .db-imported ]; then
  echo "==> import database dumps"
  for db in fleetbase fleetbase_sandbox fleetbase_storefront; do
    [ -f dump/$db.sql.gz ] || continue
    docker compose exec -T database mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    gunzip -c dump/$db.sql.gz | docker compose exec -T database mysql -uroot -p"$MYSQL_ROOT_PASSWORD" --default-character-set=utf8mb4 "$db"
    echo "   imported $db"
  done
  docker compose exec -T database mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "GRANT ALL ON fleetbase.* TO 'fleetbase'@'%'; GRANT ALL ON fleetbase_sandbox.* TO 'fleetbase'@'%'; GRANT ALL ON fleetbase_storefront.* TO 'fleetbase'@'%'; FLUSH PRIVILEGES;"
  touch .db-imported
fi
echo "==> start stack"
docker compose up -d --remove-orphans
sleep 25
docker compose exec -T application sh -c 'php artisan migrate --force && php artisan sandbox:migrate --force; php artisan fleetbase:create-permissions >/dev/null; php artisan energyops:install; php artisan schedule-monitor:sync; php artisan storage:link >/dev/null 2>&1; php artisan cache:clear && php artisan route:clear && php artisan config:clear && php artisan queue:restart'
docker compose restart queue scheduler >/dev/null
docker compose ps --format 'table {{.Name}}\t{{.Status}}'
curl -s -o /dev/null -w 'api http://172.17.0.1:8001 -> %{http_code}\n' http://172.17.0.1:8001/
curl -s -o /dev/null -w 'console http://172.17.0.1:4200 -> %{http_code}\n' http://172.17.0.1:4200/
